<?php

/**
 * Leitor da folha de gabarito impressa pela própria plataforma.
 */
return [
    'chave' => 'leitor-gabarito',
    'namespace' => 'App\\Modulos\\LeitorGabarito',
    'label' => 'Leitor de gabarito',
    'grupo' => 'geral',
    'feature_keys' => [
        'admin' => 'leitor_gabarito',
        'professor' => 'professor_leitor_gabarito',
    ],
    'master_feature_keys' => ['leitor_gabarito', 'professor_leitor_gabarito'],
    'master_form_key' => 'geral_leitor_gabarito',
    'feature_gate' => true,
    'rotas' => [
        '/admin/leitor-gabarito' => 'leitor_gabarito',
        '/professor/leitor-gabarito' => 'professor_leitor_gabarito',
    ],
    'menu' => [
        'admin' => [],
        'professor' => [],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
