<?php
/**
 * Leiaute genérico — placeholder até o documento oficial do INEP da edição ser importado.
 *
 * NÃO contém posições, tamanhos, separador oficial, tipos de registro nem códigos oficiais.
 * O gerador de TXT recusa a emissão enquanto oficial = false.
 *
 * Fonte a registrar quando disponível:
 * https://www.gov.br/inep/pt-br/areas-de-atuacao/pesquisas-estatisticas-e-indicadores/censo-escolar/orientacoes/matricula-inicial/migracao
 */
return [
    'ano' => 0,
    'versao' => 'pendente-oficial',
    'etapa_coleta' => 'matricula_inicial',
    'oficial' => false,
    'fonte_oficial' => 'https://www.gov.br/inep/pt-br/areas-de-atuacao/pesquisas-estatisticas-e-indicadores/censo-escolar/orientacoes/matricula-inicial/migracao',
    'separador' => '|',
    'codificacao' => 'ISO-8859-1',
    'quebra_linha' => "\r\n",
    'campo_vazio' => '',
    'registros' => [],
    'dominios' => [],
    'regras' => [
        [
            'codigo' => 'escola_sem_inep',
            'entidade' => 'escola',
            'campo' => 'inep',
            'severidade' => 'erro',
            'mensagem_usuario' => 'A unidade escolar não possui código INEP.',
            'orientacao_correcao' => 'Informe o código INEP no cadastro da unidade ou no complemento do Censo.',
        ],
        [
            'codigo' => 'aluno_sem_filiacao',
            'entidade' => 'aluno',
            'campo' => 'nome_mae',
            'severidade' => 'erro',
            'mensagem_usuario' => 'Aluno sem filiação (nome da mãe).',
            'orientacao_correcao' => 'Preencha o nome da mãe no cadastro do aluno.',
        ],
        [
            'codigo' => 'aluno_sem_nascimento',
            'entidade' => 'aluno',
            'campo' => 'data_nasc',
            'severidade' => 'erro',
            'mensagem_usuario' => 'Aluno sem data de nascimento.',
            'orientacao_correcao' => 'Informe a data de nascimento no cadastro do aluno.',
        ],
        [
            'codigo' => 'aluno_sem_inep',
            'entidade' => 'aluno',
            'campo' => 'codigo_inep',
            'severidade' => 'alerta',
            'mensagem_usuario' => 'Aluno sem código INEP.',
            'orientacao_correcao' => 'Gere o arquivo de identificação ou informe o código retornado pelo Educacenso.',
        ],
        [
            'codigo' => 'aluno_sem_cpf',
            'entidade' => 'aluno',
            'campo' => 'cpf',
            'severidade' => 'alerta',
            'mensagem_usuario' => 'Aluno sem CPF.',
            'orientacao_correcao' => 'Informe o CPF quando o leiaute da edição exigir, ou justifique a ausência.',
        ],
        [
            'codigo' => 'turma_sem_etapa',
            'entidade' => 'turma',
            'campo' => 'etapa_codigo',
            'severidade' => 'alerta',
            'mensagem_usuario' => 'Turma sem etapa/modalidade censitária oficial.',
            'orientacao_correcao' => 'Mapeie a série acadêmica para a etapa oficial da edição quando o leiaute estiver disponível.',
        ],
        [
            'codigo' => 'matricula_sem_turma',
            'entidade' => 'matricula',
            'campo' => 'turma_id',
            'severidade' => 'erro',
            'mensagem_usuario' => 'Matrícula sem turma.',
            'orientacao_correcao' => 'Vincule o aluno a uma turma do ano da edição.',
        ],
        [
            'codigo' => 'gestor_ausente',
            'entidade' => 'gestor',
            'campo' => 'nome',
            'severidade' => 'erro',
            'mensagem_usuario' => 'A edição não possui gestor escolar.',
            'orientacao_correcao' => 'Informe o diretor ou o gestor no cadastro da unidade.',
        ],
        [
            'codigo' => 'profissional_sem_formacao',
            'entidade' => 'profissional',
            'campo' => 'escolaridade',
            'severidade' => 'alerta',
            'mensagem_usuario' => 'Profissional sem formação cadastrada no complemento do Censo.',
            'orientacao_correcao' => 'Preencha a escolaridade e a formação no formulário do profissional.',
        ],
        [
            'codigo' => 'layout_nao_oficial',
            'entidade' => 'edicao',
            'campo' => 'versao_layout',
            'severidade' => 'erro',
            'mensagem_usuario' => 'O leiaute oficial desta edição ainda não foi importado.',
            'orientacao_correcao' => 'Importe o leiaute e as tabelas auxiliares do INEP antes de gerar o TXT.',
        ],
    ],
];
