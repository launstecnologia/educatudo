<?php
/**
 * Manifest do módulo Ocorrências do aluno.
 * Registro central da vida escolar. Opcional por escola (Master). Default ligado.
 */
return [
    'chave' => 'ocorrencias',
    'label' => 'Ocorrências do aluno',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'ocorrencias',
        'professor' => 'ocorrencias',
    ],
    'master_feature_keys' => ['ocorrencias'],
    'master_form_key' => 'geral_ocorrencias',
    'feature_defaults' => [
        'ocorrencias' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/ocorrencias' => 'ocorrencias',
        '/professor/ocorrencias' => 'ocorrencias',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Ocorrências',
                'path' => '/admin/ocorrencias',
                'feature_key' => 'ocorrencias',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
