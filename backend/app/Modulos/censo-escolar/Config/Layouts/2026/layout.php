<?php
/**
 * Leiaute oficial da Matrícula Inicial 2026 (INEP).
 * Campos e totais extraídos de layout_de_importacao_e_exportacao_2026.xlsx.
 * Tabelas auxiliares de domínio (etapa, município, componente) entram via registros.json.
 */
$json = __DIR__ . '/registros.json';
$dados = is_file($json) ? json_decode((string) file_get_contents($json), true) : [];
if (!is_array($dados)) {
    $dados = [];
}

return [
    'ano' => 2026,
    'versao' => (string) ($dados['versao'] ?? 'v3'),
    'etapa_coleta' => 'matricula_inicial',
    'oficial' => true,
    'fonte_oficial' => (string) ($dados['fonte_oficial'] ?? ''),
    'separador' => '|',
    'codificacao' => 'ISO-8859-1',
    'quebra_linha' => "\r\n",
    'campo_vazio' => '',
    'registro_final' => '99|',
    'registros' => is_array($dados['registros'] ?? null) ? $dados['registros'] : [],
    'identificacao' => is_array($dados['identificacao'] ?? null) ? $dados['identificacao'] : [],
    'dominios' => [
        'etapa' => [
            ['codigo' => '1', 'descricao' => 'Educação infantil - creche'],
            ['codigo' => '2', 'descricao' => 'Educação infantil - pré-escola'],
            ['codigo' => '14', 'descricao' => 'Ensino fundamental de 9 anos - 1º Ano'],
            ['codigo' => '15', 'descricao' => 'Ensino fundamental de 9 anos - 2º Ano'],
            ['codigo' => '16', 'descricao' => 'Ensino fundamental de 9 anos - 3º Ano'],
            ['codigo' => '17', 'descricao' => 'Ensino fundamental de 9 anos - 4º Ano'],
            ['codigo' => '18', 'descricao' => 'Ensino fundamental de 9 anos - 5º Ano'],
            ['codigo' => '19', 'descricao' => 'Ensino fundamental de 9 anos - 6º Ano'],
            ['codigo' => '20', 'descricao' => 'Ensino fundamental de 9 anos - 7º Ano'],
            ['codigo' => '21', 'descricao' => 'Ensino fundamental de 9 anos - 8º Ano'],
            ['codigo' => '41', 'descricao' => 'Ensino fundamental de 9 anos - 9º Ano'],
            ['codigo' => '25', 'descricao' => 'Ensino médio - 1ª Série'],
            ['codigo' => '26', 'descricao' => 'Ensino médio - 2ª Série'],
            ['codigo' => '27', 'descricao' => 'Ensino médio - 3ª Série'],
            ['codigo' => '28', 'descricao' => 'Ensino médio - 4ª Série'],
            ['codigo' => '29', 'descricao' => 'Ensino médio - não seriada'],
        ],
        'etapa_agregada' => [
            ['codigo' => '301', 'descricao' => 'Educação Infantil'],
            ['codigo' => '302', 'descricao' => 'Ensino Fundamental'],
            ['codigo' => '304', 'descricao' => 'Ensino Médio'],
        ],
    ],
    'regras' => is_array($dados['regras'] ?? null) ? $dados['regras'] : [],
];
