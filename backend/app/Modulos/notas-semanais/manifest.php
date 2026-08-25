<?php
/**
 * Manifest do módulo Quadro de Notas Semanais (S1–S8, média semanal, prova bim, ENAC…).
 * Opcional por escola (Master). Default ligado.
 */
return [
    'chave' => 'notas-semanais',
    'label' => 'Quadro de Notas Semanais',
    'grupo' => 'avaliacoes',
    'feature_keys' => [
        'admin' => 'notas_semanais',
        'aluno' => 'notas_semanais',
    ],
    'master_feature_keys' => ['notas_semanais'],
    'master_form_key' => 'geral_notas_semanais',
    'feature_defaults' => [
        'notas_semanais' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/notas-semanais' => 'notas_semanais',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Quadro semanal',
                'path' => '/admin/notas-semanais',
                'feature_key' => 'notas_semanais',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
