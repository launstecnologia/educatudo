<?php
/**
 * Manifest do módulo Conselho de Classe.
 * Opcional por escola (Master). Default ligado.
 * Homologação de resultados finais continua no core e só consulta as tabelas se existirem.
 */
return [
    'chave' => 'conselho-classe',
    'label' => 'Conselho de Classe',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'conselho_classe',
        'professor' => 'conselho_classe',
    ],
    'master_feature_keys' => ['conselho_classe'],
    'master_form_key' => 'geral_conselho_classe',
    'feature_defaults' => [
        'conselho_classe' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/conselhos' => 'conselho_classe',
        '/professor/conselhos' => 'conselho_classe',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Conselho de Classe',
                'path' => '/admin/conselhos',
                'feature_key' => 'conselho_classe',
            ],
        ],
        'professor' => [
            [
                'label' => 'Conselho de Classe',
                'path' => '/professor/conselhos',
                'feature_key' => 'conselho_classe',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
