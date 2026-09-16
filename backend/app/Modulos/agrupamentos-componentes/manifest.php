<?php
/**
 * Manifest — Agrupamento de Disciplinas (legado do boletim).
 * Cadastro saiu da UI: a área oficial é o componente pai em Componentes Curriculares.
 */
return [
    'chave' => 'agrupamentos-componentes',
    'label' => 'Agrupamento de Disciplinas',
    'grupo' => 'academico',
    'feature_keys' => [
        'admin' => 'agrupamentos_componentes',
    ],
    'master_feature_keys' => [],
    'master_form_key' => '',
    'feature_defaults' => [
        'agrupamentos_componentes' => '0',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/agrupamentos-componentes' => 'agrupamentos_componentes',
    ],
    'menu' => [
        'admin' => [],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
