<?php
/**
 * Manifest do módulo Regras Acadêmicas (CRUD admin).
 * Sempre disponível — o motor (ResultadoAcademicoService) fica no core
 * e o boletim/histórico não dependem de FeatureGate.
 */
return [
    'chave' => 'regras-academicas',
    'label' => 'Regras Acadêmicas',
    'grupo' => 'academico',
    'feature_keys' => [],
    'feature_gate' => false,
    'rotas' => [
        '/admin/regras-academicas' => null,
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Regras acadêmicas',
                'path' => '/admin/regras-academicas',
                'feature_key' => null,
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
