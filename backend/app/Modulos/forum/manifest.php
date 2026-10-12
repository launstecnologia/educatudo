<?php
/**
 * Manifest do módulo Fórum.
 */
return [
    'chave' => 'forum',
    'label' => 'Fórum',
    'namespace' => 'App\\Modulos\\Forum',
    'feature_gate' => true,
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
