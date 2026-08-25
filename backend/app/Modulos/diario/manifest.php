<?php
/**
 * Manifest do módulo Diário de Classe (professor + coordenação).
 * Opcional por escola (Master). Default ligado.
 */
return [
    'chave' => 'diario',
    'label' => 'Diário de Classe',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'diario_classe',
        'professor' => 'diario_classe',
    ],
    'master_feature_keys' => ['diario_classe'],
    'master_form_key' => 'geral_diario_classe',
    'feature_defaults' => [
        'diario_classe' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/diario' => 'diario_classe',
        '/professor/diario' => 'diario_classe',
        '/professor/diarios' => 'diario_classe',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Diário de Classe',
                'path' => '/admin/diario',
                'feature_key' => 'diario_classe',
            ],
        ],
        'professor' => [
            [
                'label' => 'Diários de Classe',
                'path' => '/professor/diarios',
                'feature_key' => 'diario_classe',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
