<?php
/**
 * Manifest — Evento Avaliativo.
 * Default desligado: a escola continua com Tipos de Nota, Quadro e Lançamento.
 * Ligado no Master, esses três menus somem e entra Eventos Avaliativos.
 */
return [
    'chave' => 'eventos-avaliativos',
    'label' => 'Modelo Evento Avaliativo',
    'namespace' => 'App\\Modulos\\EventosAvaliativos',
    'grupo' => 'academico',
    'feature_keys' => [
        'admin' => 'eventos_avaliativos',
    ],
    'master_feature_keys' => ['eventos_avaliativos'],
    'master_form_key' => 'geral_eventos_avaliativos',
    'feature_defaults' => [
        'eventos_avaliativos' => '0',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/eventos-avaliativos' => 'eventos_avaliativos',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Eventos Avaliativos',
                'path' => '/admin/eventos-avaliativos',
                'feature_key' => 'eventos_avaliativos',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
