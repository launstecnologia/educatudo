<?php
/**
 * Login LEX (OAuth da escola). A configuração fica no Master.
 */
return [
    'chave' => 'lex',
    'label' => 'LEX',
    'namespace' => 'App\\Modulos\\Lex',
    'grupo' => 'produtos',
    'feature_gate' => false,
    'controllers_dir' => __DIR__ . '/Controllers',
];
