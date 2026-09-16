<?php
/**
 * Manifest — Quadro de Notas (blocos e colunas dinâmicos por escola).
 * Default ligado: escola que não cadastrar grupo não vê colunas extras.
 */
return [
    'chave' => 'grupos-regras-notas',
    'label' => 'Quadro de Notas',
    'grupo' => 'academico',
    'feature_keys' => [
        'admin' => 'grupos_regras_notas',
    ],
    'master_feature_keys' => ['grupos_regras_notas'],
    'master_form_key' => 'geral_grupos_regras_notas',
    'feature_defaults' => [
        'grupos_regras_notas' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/grupos-regras-notas' => 'grupos_regras_notas',
        '/admin/quadros-notas' => 'grupos_regras_notas',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Quadro de Notas',
                'path' => '/admin/grupos-regras-notas',
                'feature_key' => 'grupos_regras_notas',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
