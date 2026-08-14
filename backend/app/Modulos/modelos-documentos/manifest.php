<?php
/**
 * Manifest do módulo Modelos de Documentos (contratos e textos editáveis).
 * Sempre disponível (não gated por FeatureGate) — como documentos institucionais.
 */
return [
    'chave' => 'modelos-documentos',
    'label' => 'Modelos de Documentos',
    'grupo' => 'secretaria',
    'feature_keys' => [],
    'feature_gate' => false,
    'rotas' => [
        '/admin/modelos-documentos' => null,
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Modelos de documentos',
                'path' => '/admin/modelos-documentos',
                'feature_key' => null,
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
