<?php
/**
 * Manifest do módulo Censo Escolar / Educacenso.
 * Opcional por escola (Master). Default ligado.
 */
return [
    'chave' => 'censo-escolar',
    'label' => 'Censo Escolar',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'censo_escolar',
    ],
    'master_feature_keys' => ['censo_escolar'],
    'master_form_key' => 'geral_censo_escolar',
    'feature_defaults' => [
        'censo_escolar' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/censo' => 'censo_escolar',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Censo Escolar',
                'path' => '/admin/censo',
                'feature_key' => 'censo_escolar',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
