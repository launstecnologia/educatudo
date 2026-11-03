<?php
/**
 * Carteirinha escolar. O Master oferece o módulo; a escola liga o uso no admin.
 */
return [
    'chave' => 'carteirinha',
    'namespace' => 'App\\Modulos\\Carteirinha',
    'label' => 'Carteirinha',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'carteirinha',
        'aluno' => 'carteirinha',
    ],
    'master_feature_keys' => ['carteirinha'],
    'master_form_key' => 'geral_carteirinha',
    'feature_defaults' => [
        'carteirinha' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/carteirinha' => 'carteirinha',
        '/aluno/carteirinha' => 'carteirinha',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Carteirinha',
                'path' => '/admin/carteirinha',
                'feature_key' => 'carteirinha',
            ],
        ],
        'aluno' => [
            [
                'label' => 'Carteirinha',
                'path' => '/aluno/carteirinha',
                'feature_key' => 'carteirinha',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
