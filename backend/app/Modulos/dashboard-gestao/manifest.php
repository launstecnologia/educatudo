<?php
/**
 * Manifest do Dashboard de Gestão Escolar.
 * Sempre ligado — é a home do admin. Não duplica FeatureGate.
 */
return [
    'chave' => 'dashboard-gestao',
    'label' => 'Dashboard de Gestão Escolar',
    'grupo' => 'dashboard',
    'feature_keys' => [],
    'feature_gate' => false,
    'rotas' => [
        '/admin/dashboard' => null,
    ],
    'menu' => [],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
