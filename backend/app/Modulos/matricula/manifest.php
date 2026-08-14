<?php
/**
 * Manifest do módulo Processo de Matrícula (secretaria).
 * Feature opcional: escolas só com IA usam cadastro simples de aluno (Master).
 */
return [
    'chave' => 'matricula',
    'label' => 'Processo de Matrícula',
    'grupo' => 'secretaria',
    'feature_keys' => [
        'admin' => 'processo_matricula',
    ],
    'master_feature_keys' => ['processo_matricula'],
    'master_form_key' => 'geral_processo_matricula',
    // Default on: escolas que já usavam /admin/enrollment não perdem o menu.
    // Escolas só com IA desligam no Master e mantêm "Cadastro simples de aluno".
    'feature_defaults' => [
        'processo_matricula' => '1',
    ],
    'feature_gate' => true,
    'rotas' => [
        '/admin/enrollment' => 'processo_matricula',
        '/admin/matricula' => 'processo_matricula',
        '/matricula/interesse' => 'processo_matricula',
    ],
    'menu' => [
        'admin' => [
            [
                'label' => 'Matrículas',
                'path' => '/admin/enrollment',
                'feature_key' => 'processo_matricula',
            ],
        ],
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
