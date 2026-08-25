<?php
/**
 * Manifest do módulo Presença (catraca, secretaria, consolidado de faltas).
 * Opcional por escola (Master). Default ligado.
 */
return [
    'chave' => 'presenca',
    'label' => 'Presença',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'presenca',
    ],
    'master_feature_keys' => ['presenca'],
    'master_form_key' => 'geral_presenca',
    'feature_defaults' => [
        'presenca' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/presenca' => 'presenca',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Presença',
                'path' => '/admin/presenca',
                'feature_key' => 'presenca',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
