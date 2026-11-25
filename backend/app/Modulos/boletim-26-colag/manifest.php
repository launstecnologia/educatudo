<?php
/**
 * Boletim 2026 do Colag — demonstrativo estático, só desta escola.
 * Não altera o assistente de boletins nem o módulo Evento Avaliativo.
 */
return [
    'chave' => 'boletim-26-colag',
    'label' => 'Boletim Colag',
    'namespace' => 'App\\Modulos\\Boletim26Colag',
    'grupo' => 'colag',
    'feature_keys' => [
        'admin' => 'boletim_26_colag',
    ],
    'master_feature_keys' => [],
    'feature_defaults' => [
        'boletim_26_colag' => '0',
    ],
    'feature_gate' => false,
    'rotas' => [],
    'menu' => [],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
