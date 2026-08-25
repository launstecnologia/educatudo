<?php
$hub_title = 'Financeiro';
$hub_subtitle = 'Contratos, cobranças, fluxo de caixa e relatórios da escola.';
$hub_cards = [
    [
        'href' => URL . '/admin/finance',
        'title' => 'Dashboard',
        'description' => 'Visão geral de contratos, cobranças e inadimplência.',
        'icon' => 'fa-solid fa-gauge-high',
    ],
    [
        'href' => URL . '/admin/finance/contracts',
        'title' => 'Contratos',
        'description' => 'Cadastre e gerencie os contratos de mensalidade.',
        'icon' => 'fa-solid fa-file-contract',
    ],
    [
        'href' => URL . '/admin/finance/plans',
        'title' => 'Planos e Preços',
        'description' => 'Configure planos, valores e tabela de preços.',
        'icon' => 'fa-solid fa-layer-group',
    ],
    [
        'href' => URL . '/admin/finance/charges',
        'title' => 'Cobranças Avulsas',
        'description' => 'Gere cobranças pontuais fora do contrato mensal.',
        'icon' => 'fa-solid fa-bolt',
    ],
    [
        'href' => URL . '/admin/finance/charges/batch',
        'title' => 'Nova em Lote',
        'description' => 'Crie cobranças em lote para vários alunos.',
        'icon' => 'fa-solid fa-layer-group',
    ],
    [
        'href' => URL . '/admin/finance/bills',
        'title' => 'Contas a Pagar',
        'description' => 'Controle despesas e contas a pagar da escola.',
        'icon' => 'fa-solid fa-file-invoice-dollar',
    ],
    [
        'href' => URL . '/admin/finance/cashflow',
        'title' => 'Fluxo de Caixa',
        'description' => 'Acompanhe entradas e saídas no período.',
        'icon' => 'fa-solid fa-water',
    ],
    [
        'href' => URL . '/admin/finance/reports/dre',
        'title' => 'DRE',
        'description' => 'Demonstração do resultado do exercício.',
        'icon' => 'fa-solid fa-chart-bar',
    ],
    [
        'href' => URL . '/admin/finance/reports/dfc',
        'title' => 'DFC',
        'description' => 'Demonstração dos fluxos de caixa.',
        'icon' => 'fa-solid fa-arrow-right-arrow-left',
    ],
    [
        'href' => URL . '/admin/finance/reports/balanco',
        'title' => 'Balanço Patrimonial',
        'description' => 'Posição patrimonial da escola.',
        'icon' => 'fa-solid fa-scale-balanced',
    ],
    [
        'href' => URL . '/admin/finance/reports/dmpl',
        'title' => 'DMPL',
        'description' => 'Demonstração das mutações do patrimônio líquido.',
        'icon' => 'fa-solid fa-table',
    ],
    [
        'href' => URL . '/admin/finance/reports/dlpa',
        'title' => 'DLPA',
        'description' => 'Demonstração de lucros ou prejuízos acumulados.',
        'icon' => 'fa-solid fa-trophy',
    ],
    [
        'href' => URL . '/admin/finance/discount-rules',
        'title' => 'Descontos',
        'description' => 'Regras e concessões de desconto nos contratos.',
        'icon' => 'fa-solid fa-percent',
    ],
    [
        'href' => URL . '/admin/finance/report/inadimplencia',
        'title' => 'Inadimplência',
        'description' => 'Alunos e contratos com mensalidades em atraso.',
        'icon' => 'fa-solid fa-chart-line',
    ],
    [
        'href' => URL . '/admin/finance/settings',
        'title' => 'Configurações',
        'description' => 'Parâmetros e integração do módulo financeiro.',
        'icon' => 'fa-solid fa-sliders',
    ],
];
include __DIR__ . '/../_partials/hub_modulos.php';
