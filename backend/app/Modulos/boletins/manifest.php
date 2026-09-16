<?php
/**
 * Manifest — Cadastro de Modelo de Boletim (oficial / extra), separado das avaliações.
 */
return [
    'chave' => 'boletins',
    'label' => 'Modelo de Boletim',
    'grupo' => 'academico',
    'feature_keys' => [
        'admin' => 'boletim',
    ],
    'master_feature_keys' => ['boletim'],
    'master_form_key' => 'geral_boletim',
    'feature_defaults' => [
        'boletim' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/boletins' => 'boletim',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Modelo de Boletim',
                'path' => '/admin/boletins',
                'feature_key' => 'boletim',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
