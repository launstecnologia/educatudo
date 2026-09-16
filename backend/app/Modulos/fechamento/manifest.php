<?php
/**
 * Manifest do módulo Fechamento (painel, homologações, documentos do período).
 * Reusa o FeatureGate de Resultados Finais — não é módulo opcional separado.
 */
return [
    'chave' => 'fechamento',
    'label' => 'Fechamento',
    'grupo' => 'academico',
    'feature_keys' => [
        'admin' => 'resultados_finais',
    ],
    'master_feature_keys' => ['resultados_finais'],
    'master_form_key' => 'geral_resultados_finais',
    'feature_defaults' => [
        'resultados_finais' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/fechamento' => 'resultados_finais',
        '/admin/homologacoes' => 'resultados_finais',
        '/admin/documentos-periodo' => 'resultados_finais',
        '/admin/diagnostico-menu' => 'resultados_finais',
        '/admin/implantar-academico' => 'resultados_finais',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Fechamento',
                'path' => '/admin/fechamento',
                'feature_key' => 'resultados_finais',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
