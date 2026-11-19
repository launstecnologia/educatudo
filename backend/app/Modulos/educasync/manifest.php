<?php
/**
 * Manifest do módulo EducaSync (grade horária por otimização).
 * Gera rascunhos com um solver externo e publica em grade_horaria; não substitui a Grade Horária do Domínio Escola.
 * Opcional por escola (Master). Default desligado (módulo pago).
 */
return [
    'chave' => 'educasync',
    'label' => 'EducaSync',
    'namespace' => 'App\\Modulos\\EducaSync',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'educasync',
    ],
    'master_feature_keys' => ['educasync'],
    'master_form_key' => 'geral_educasync',
    'feature_defaults' => [
        'educasync' => '0',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/educasync' => 'educasync',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'EducaSync',
                'path' => '/admin/educasync',
                'feature_key' => 'educasync',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
