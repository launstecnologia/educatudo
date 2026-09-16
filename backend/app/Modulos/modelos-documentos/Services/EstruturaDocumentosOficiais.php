<?php

namespace App\Modulos\ModelosDocumentos\Services;

/**
 * Layouts visuais (A4) dos documentos oficiais do fechamento,
 * alinhados aos modelos de referência da secretaria.
 */
class EstruturaDocumentosOficiais
{
    /**
     * @return array<string,mixed>|null
     */
    public static function paraCodigo(string $codigo): ?array
    {
        $codigo = strtolower(trim($codigo));
        return match (true) {
            $codigo === 'resultado_boletim_padrao',
            $codigo === 'vida_escolar_boletim',
            str_contains($codigo, 'boletim') => self::boletim(),
            $codigo === 'resultado_ficha_individual' => self::ficha(),
            $codigo === 'resultado_ata_finais' => self::ata(),
            $codigo === 'resultado_historico',
            $codigo === 'declaracao_historico',
            $codigo === 'vida_escolar_historico' => self::historico(),
            $codigo === 'declaracao_conclusao' => self::conclusao(),
            $codigo === 'resultado_certificado_conclusao' => self::certificado(),
            $codigo === 'resultado_diploma' => self::diploma(),
            default => null,
        };
    }

    /** @return array<string,mixed> */
    public static function boletim(): array
    {
        $est = self::base('retrato');
        self::cabecalho($est, 'BOLETIM ESCOLAR FINAL');
        $est['body']['sections'] = [
            self::secaoHtml(
                '<table class="dados">'
                . '<tr><td class="label">Aluno</td><td>{{aluno_nome}}</td><td class="label">RA</td><td>{{aluno_codigo}}</td></tr>'
                . '<tr><td class="label">Ano letivo</td><td>{{ano_letivo}}</td><td class="label">Turma</td><td>{{turma_nome}}</td></tr>'
                . '<tr><td class="label">Etapa</td><td>{{etapa}}</td><td class="label">Turno</td><td>{{turno}}</td></tr>'
                . '</table>'
            ),
            self::secaoTitulo('RESULTADOS POR COMPONENTE CURRICULAR'),
            self::secaoBloco('tabela_notas'),
            self::secaoHtml(
                '<table class="dados">'
                . '<tr><td class="label">Carga horária</td><td>{{carga_horaria_total}}</td>'
                . '<td class="label">Frequência geral</td><td>{{frequencia_percentual}}</td></tr>'
                . '<tr><td class="label">Resultado final</td><td>{{situacao_final}}</td>'
                . '<td class="label">Próxima série</td><td>{{proxima_serie}}</td></tr>'
                . '</table>'
            ),
        ];
        self::assinaturas($est);
        return $est;
    }

    /** @return array<string,mixed> */
    public static function ficha(): array
    {
        $est = self::base('retrato');
        self::cabecalho($est, 'FICHA INDIVIDUAL / VIDA ESCOLAR');
        $est['body']['sections'] = [
            self::secaoTitulo('IDENTIFICAÇÃO DO ALUNO'),
            self::secaoHtml(
                '<table class="dados">'
                . '<tr><td class="label">Nome</td><td>{{aluno_nome}}</td><td class="label">RA</td><td>{{aluno_codigo}}</td></tr>'
                . '<tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td><td class="label">Naturalidade</td><td>{{aluno_naturalidade}}</td></tr>'
                . '<tr><td class="label">Filiação</td><td>{{aluno_filiacao}}</td><td class="label">Ano letivo</td><td>{{ano_letivo}}</td></tr>'
                . '<tr><td class="label">Curso/Etapa</td><td>{{etapa}}</td><td class="label">Turma</td><td>{{turma_nome}}</td></tr>'
                . '</table>'
            ),
            self::secaoTitulo('REGISTRO DO ANO LETIVO'),
            self::secaoBloco('tabela_notas'),
            self::secaoHtml(
                '<table class="dados">'
                . '<tr><td class="label">Dias letivos</td><td>{{dias_letivos}}</td>'
                . '<td class="label">Frequência</td><td>{{frequencia_percentual}}</td></tr>'
                . '<tr><td class="label">Resultado</td><td>{{situacao_final}}</td>'
                . '<td class="label">Conselho final</td><td>{{conselho_label}}</td></tr>'
                . '</table>'
            ),
            self::secaoBloco('observacoes', true),
        ];
        self::assinaturas($est);
        return $est;
    }

    /** @return array<string,mixed> */
    public static function ata(): array
    {
        $est = self::base('retrato');
        self::cabecalho($est, 'ATA DE RESULTADOS FINAIS');
        $est['body']['sections'] = [
            self::secaoTexto(
                'Aos {{data_extenso}}, após o encerramento do ano letivo e a consolidação dos registros acadêmicos, '
                . 'foram homologados os resultados finais da turma abaixo.'
            ),
            self::secaoHtml(
                '<table class="dados">'
                . '<tr><td class="label">Ano letivo</td><td>{{ano_letivo}}</td>'
                . '<td class="label">Turma</td><td>{{turma_nome}}</td></tr>'
                . '<tr><td class="label">Etapa</td><td>{{etapa}}</td>'
                . '<td class="label">Turno</td><td>{{turno}}</td></tr>'
                . '</table>'
            ),
            self::secaoBloco('tabela_coletiva'),
            self::secaoTexto('{{ata_totais}}'),
            self::secaoTexto('Espaço adicional para assinaturas do Conselho de Classe, quando previsto pela instituição.'),
        ];
        self::assinaturas($est);
        return $est;
    }

    /** @return array<string,mixed> */
    public static function historico(): array
    {
        $est = self::base('retrato');
        self::cabecalho($est, 'HISTÓRICO ESCOLAR');
        $est['body']['sections'] = [
            self::secaoTitulo('DADOS DO ALUNO'),
            self::secaoHtml(
                '<table class="dados">'
                . '<tr><td class="label">Nome</td><td>{{aluno_nome}}</td><td class="label">RA</td><td>{{aluno_codigo}}</td></tr>'
                . '<tr><td class="label">Nascimento</td><td>{{aluno_data_nasc}}</td><td class="label">Naturalidade</td><td>{{aluno_naturalidade}}</td></tr>'
                . '<tr><td class="label">Documento</td><td>CPF/CIN: {{aluno_cpf}}</td><td class="label">Nacionalidade</td><td>{{aluno_nacionalidade}}</td></tr>'
                . '</table>'
            ),
            self::secaoTitulo('TRAJETÓRIA ESCOLAR'),
            self::secaoBloco('historico'),
            self::secaoTitulo('RESULTADOS DO ÚLTIMO ANO CURSADO'),
            self::secaoBloco('tabela_notas'),
            self::secaoBloco('observacoes', true),
        ];
        self::assinaturas($est);
        return $est;
    }

    /** @return array<string,mixed> */
    public static function conclusao(): array
    {
        $est = self::base('retrato');
        self::cabecalho($est, 'DECLARAÇÃO DE CONCLUSÃO DE ANO/SÉRIE');
        $est['body']['sections'] = [
            self::secaoHtml(
                '<div class="corpo"><p>Declaramos, para os devidos fins, que '
                . '<strong>{{aluno_nome}}</strong>, RA nº {{aluno_codigo}}, concluiu com aproveitamento '
                . 'a <strong>{{serie}}</strong>{{turma_frase}}, no ano letivo de <strong>{{ano_letivo}}</strong>, '
                . 'nesta instituição de ensino, tendo sido considerado(a) <strong>{{situacao_final}}</strong> '
                . 'ao final do período letivo.</p>'
                . '<p>A presente declaração é expedida a pedido do interessado para fins de comprovação de escolaridade.</p></div>'
            ),
            self::secaoTexto('{{cidade_data}}.'),
            self::secaoTexto('Código de validação: {{codigo_validacao}}'),
        ];
        self::assinaturas($est);
        return $est;
    }

    /** @return array<string,mixed> */
    public static function certificado(): array
    {
        $est = self::base('retrato');
        self::cabecalho($est, 'CERTIFICADO DE CONCLUSÃO');
        $est['body']['sections'] = [
            self::secaoHtml(
                '<div class="corpo"><p>O(A) <strong>{{escola_nome}}</strong> certifica que '
                . '<strong>{{aluno_nome}}</strong>{{aluno_nasc_frase}} concluiu a etapa '
                . '<strong>{{etapa}}</strong> no ano letivo de <strong>{{ano_letivo}}</strong>, '
                . 'cumprindo as exigências acadêmicas previstas para a etapa de ensino.</p>'
                . '<p>Por ser expressão da verdade, expede-se o presente certificado para que produza os efeitos cabíveis.</p></div>'
            ),
            self::secaoTexto('{{cidade_data}}.'),
            self::secaoTexto('Registro interno nº {{numero}}/{{ano}} | Código de validação: {{codigo_validacao}}'),
        ];
        self::assinaturas($est);
        return $est;
    }

    /** @return array<string,mixed> */
    public static function diploma(): array
    {
        $est = self::base('retrato');
        self::cabecalho($est, 'DIPLOMA');
        $est['body']['sections'] = [
            self::secaoHtml(
                '<div class="corpo"><p>O(A) <strong>{{escola_nome}}</strong>, no uso de suas atribuições, confere a '
                . '<strong>{{aluno_nome}}</strong> o presente diploma de <strong>{{etapa}}</strong>, '
                . 'por haver concluído integralmente o curso e cumprido os requisitos acadêmicos correspondentes '
                . 'no ano de <strong>{{ano_letivo}}</strong>.</p>'
                . '<p>Este documento aplica-se às modalidades em que a expedição de diploma seja exigida.</p></div>'
            ),
            self::secaoHtml(
                '<table class="dados">'
                . '<tr><td class="label">Carga horária total</td><td>{{carga_horaria_total}}</td>'
                . '<td class="label">Conclusão</td><td>{{data_hoje}}</td></tr>'
                . '<tr><td class="label">Registro</td><td>{{numero}}/{{ano}}</td>'
                . '<td class="label">Validação</td><td>{{codigo_validacao}}</td></tr>'
                . '</table>'
            ),
        ];
        self::assinaturas($est, 'Diretor(a) Escolar', 'Responsável pelo Registro');
        $verso = ModeloDocumentoService::secaoPadrao([100], 'body');
        $verso['pageBreakBefore'] = true;
        $verso['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura(
            'titulo',
            ['text' => 'VERSO / REGISTRO DO DIPLOMA', 'tag' => 'h2'],
            ['textAlign' => 'center', 'fontWeight' => 'bold']
        );
        $verso['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura(
            'texto',
            ['text' => 'Campo para ato autorizativo, registro, livro/folha, data, observações e demais informações exigidas pela modalidade e pelo órgão competente.'],
            ['fontSize' => 10, 'color' => '#4b5563']
        );
        $est['body']['sections'][] = $verso;
        return $est;
    }

    /** @return array<string,mixed> */
    private static function base(string $orientacao): array
    {
        return ModeloDocumentoService::estruturaVazia('a4', $orientacao, 14);
    }

    /** @param array<string,mixed> $est */
    private static function cabecalho(array &$est, string $titulo): void
    {
        $linha = ModeloDocumentoService::secaoPadrao([22, 78], 'header');
        $linha['columns'][0]['vAlign'] = 'middle';
        $linha['columns'][1]['vAlign'] = 'middle';
        $linha['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura(
            'logo',
            ['width' => 88, 'align' => 'center', 'vAlign' => 'middle']
        );
        $linha['columns'][1]['elements'] = [
            ModeloDocumentoService::elementoEstrutura(
                'texto',
                ['text' => '{{escola_nome}}'],
                ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => 13]
            ),
            ModeloDocumentoService::elementoEstrutura(
                'texto',
                ['text' => '{{escola_endereco}}'],
                ['textAlign' => 'center', 'fontSize' => 9, 'color' => '#4b5563']
            ),
            ModeloDocumentoService::elementoEstrutura(
                'texto',
                ['text' => '{{escola_docs}}'],
                ['textAlign' => 'center', 'fontSize' => 9, 'color' => '#4b5563']
            ),
        ];
        $tit = ModeloDocumentoService::secaoPadrao([100], 'header');
        $tit['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura(
            'titulo',
            ['text' => $titulo, 'tag' => 'h1'],
            ['textAlign' => 'center', 'fontWeight' => 'bold']
        );
        $est['header']['sections'] = [$linha, $tit];
    }

    /** @param array<string,mixed> $est */
    private static function assinaturas(
        array &$est,
        string $cargoEsq = 'Secretário(a) Escolar',
        string $cargoDir = 'Diretor(a) Escolar'
    ): void {
        $htmlEsq = '<p style="text-align:center;margin:28px 0 0;">________________________</p>'
            . '<p style="text-align:center;margin:4px 0 0;font-weight:bold;">{{secretario_nome}}</p>'
            . '<p style="text-align:center;margin:0;font-size:9pt;color:#4b5563;">' . $cargoEsq . '</p>';
        $htmlDir = '<p style="text-align:center;margin:28px 0 0;">________________________</p>'
            . '<p style="text-align:center;margin:4px 0 0;font-weight:bold;">{{diretor_nome}}</p>'
            . '<p style="text-align:center;margin:0;font-size:9pt;color:#4b5563;">' . $cargoDir . '</p>';
        $ass = ModeloDocumentoService::secaoPadrao([50, 50], 'footer');
        $ass['columns'][0]['vAlign'] = 'bottom';
        $ass['columns'][1]['vAlign'] = 'bottom';
        $ass['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('texto_rico', ['html' => $htmlEsq], ['textAlign' => 'center']);
        $ass['columns'][1]['elements'][] = ModeloDocumentoService::elementoEstrutura('texto_rico', ['html' => $htmlDir], ['textAlign' => 'center']);
        $est['footer']['sections'] = [$ass];
    }

    /** @return array<string,mixed> */
    private static function secaoHtml(string $html): array
    {
        $sec = ModeloDocumentoService::secaoPadrao([100], 'body');
        $sec['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura('html', ['html' => $html]);
        return $sec;
    }

    /** @return array<string,mixed> */
    private static function secaoTexto(string $texto): array
    {
        $sec = ModeloDocumentoService::secaoPadrao([100], 'body');
        $sec['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura(
            'texto',
            ['text' => $texto],
            ['fontSize' => 11]
        );
        return $sec;
    }

    /** @return array<string,mixed> */
    private static function secaoTitulo(string $texto): array
    {
        $sec = ModeloDocumentoService::secaoPadrao([100], 'body');
        $sec['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura(
            'titulo',
            ['text' => $texto, 'tag' => 'h3'],
            ['fontWeight' => 'bold', 'fontSize' => 11]
        );
        return $sec;
    }

    /** @return array<string,mixed> */
    private static function secaoBloco(string $tipo, bool $ocultarVazio = false): array
    {
        $sec = ModeloDocumentoService::secaoPadrao([100], 'body');
        $sec['columns'][0]['elements'][] = ModeloDocumentoService::elementoEstrutura($tipo, [], ['fontSize' => 8], $ocultarVazio);
        return $sec;
    }
}
