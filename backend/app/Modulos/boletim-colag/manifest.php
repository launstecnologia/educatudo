<?php
/**
 * Boletim Colag — modelo próprio da escola.
 * Não é o módulo Evento Avaliativo. Só o tenant colag usa este quadro
 * (Blocos A e B, prova semanal e blocos de cálculo).
 */
return [
    'chave' => 'boletim-colag',
    'label' => 'Boletim Colag',
    'namespace' => 'App\\Modulos\\BoletimColag',
    'grupo' => 'colag',
    'feature_keys' => [
        'admin' => 'boletim_colag',
    ],
    'master_feature_keys' => ['boletim_colag'],
    'master_form_key' => 'geral_boletim_colag',
    'feature_defaults' => [
        'boletim_colag' => '0',
    ],
    'feature_gate' => false,
    'rotas' => [
        '/admin/boletim-colag' => 'boletim_colag',
    ],
    'menu' => [],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
