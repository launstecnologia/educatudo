<?php
/**
 * Manifest do módulo Vida Escolar (prontuário do aluno: boletim, histórico, SED/INEP).
 * Opcional por escola (Master). Default ligado.
 */
return [
    'chave' => 'vida-escolar',
    'label' => 'Vida Escolar',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'vida_escolar',
        'aluno' => 'vida_escolar',
        'pais' => 'vida_escolar',
    ],
    'master_feature_keys' => ['vida_escolar'],
    'master_form_key' => 'geral_vida_escolar',
    'feature_defaults' => [
        'vida_escolar' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/vida-escolar' => 'vida_escolar',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Vida Escolar',
                'path' => '/admin/vida-escolar',
                'feature_key' => 'vida_escolar',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
