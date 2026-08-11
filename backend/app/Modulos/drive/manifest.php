<?php
/**
 * Manifest do módulo Drive (espaço de arquivos do aluno/professor).
 */
return [
    'chave' => 'drive',
    'label' => 'Drive',
    'grupo' => 'aluno',
    'feature_keys' => [
        'aluno' => 'drive',
    ],
    'master_aluno' => [
        'drive' => 'Drive',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/drive' => 'drive',
        '/professor/drive' => 'drive',
    ],
    'menu' => [
        'aluno' => [
            ['label' => 'Drive', 'path' => '/drive', 'feature_key' => 'drive'],
        ],
        'professor' => [
            ['label' => 'Drive', 'path' => '/professor/drive', 'feature_key' => 'drive'],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
