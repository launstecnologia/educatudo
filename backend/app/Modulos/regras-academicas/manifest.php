<?php
/**
 * Manifest do módulo Regras Acadêmicas (CRUD admin).
 * O motor (ResultadoAcademicoService) fica no core e o boletim/histórico
 * não dependem deste gate — só a tela de cadastro some se desligado no Master.
 */
return [
    'chave' => 'regras-academicas',
    'label' => 'Regras Acadêmicas',
    'grupo' => 'academico',
    'feature_keys' => [
        'admin' => 'regras_academicas',
    ],
    'master_feature_keys' => ['regras_academicas'],
    'master_form_key' => 'geral_regras_academicas',
    'feature_defaults' => [
        'regras_academicas' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/regras-academicas' => 'regras_academicas',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Regras acadêmicas',
                'path' => '/admin/regras-academicas',
                'feature_key' => 'regras_academicas',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
