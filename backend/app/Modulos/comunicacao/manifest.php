<?php
/**
 * Manifest do módulo Comunicação (escolar, reuniões e hub do menu).
 * Calendário escolar permanece no mesmo controller (rotas com feature própria no catálogo).
 */
return [
    'chave' => 'comunicacao',
    'label' => 'Comunicação',
    'namespace' => 'App\\Modulos\\Comunicacao',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'comunicacao',
    ],
    'master_feature_keys' => ['comunicacao'],
    'master_form_key' => 'geral_comunicacao',
    'feature_defaults' => [
        'comunicacao' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/comunicacao' => 'comunicacao',
        '/admin/comunicacao-escolar' => 'comunicacao',
        '/admin/reunioes' => 'comunicacao',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Comunicação',
                'path' => '/admin/comunicacao',
                'feature_key' => 'comunicacao',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
