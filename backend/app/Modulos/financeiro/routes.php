<?php
/**
 * Rotas admin do módulo Financeiro.
 *
 * @var Router $router
 */

$router->post('/admin/dev/financeiro/save', 'Modulos/financeiro/FinanceAdminController@salvarFinanceiroConfig');
$router->get('/admin/finance',                                      'Modulos/financeiro/FinanceController@dashboard');
$router->post('/admin/finance/disparar-regua',                      'Modulos/financeiro/FinanceController@dispararRegua');
$router->get('/admin/finance/contracts',                            'Modulos/financeiro/FinanceController@contractIndex');
$router->get('/admin/finance/contracts/create',                     'Modulos/financeiro/FinanceController@contractCreate');
$router->post('/admin/finance/contracts',                           'Modulos/financeiro/FinanceController@contractStore');
$router->get('/admin/finance/contracts/{id}',                       'Modulos/financeiro/FinanceController@contractShow');
$router->post('/admin/finance/contracts/{id}/activate',             'Modulos/financeiro/FinanceController@contractActivate');
$router->post('/admin/finance/contracts/{id}/cancel',               'Modulos/financeiro/FinanceController@contractCancel');
$router->post('/admin/finance/contracts/{id}/discounts/add',        'Modulos/financeiro/FinanceController@discountAdd');
$router->post('/admin/finance/contracts/{contractId}/discounts/{discountId}/approve', 'Modulos/financeiro/FinanceController@discountApprove');
$router->post('/admin/finance/contracts/{contractId}/discounts/{discountId}/reject',  'Modulos/financeiro/FinanceController@discountReject');
$router->post('/admin/finance/contracts/{contractId}/discounts/{discountId}/remove',  'Modulos/financeiro/FinanceController@discountRemove');
$router->get('/admin/finance/installments/{id}',                    'Modulos/financeiro/FinanceController@installmentShow');
$router->post('/admin/finance/installments/{id}/pay',               'Modulos/financeiro/FinanceController@installmentPay');
$router->get('/admin/finance/installments/{id}/boleto',             'Modulos/financeiro/FinanceController@installmentBoleto');
$router->get('/admin/finance/price-table',                          'Modulos/financeiro/FinanceController@priceTable');
$router->post('/admin/finance/price-table',                         'Modulos/financeiro/FinanceController@priceTableStore');
$router->post('/admin/finance/price-table/{id}/delete',             'Modulos/financeiro/FinanceController@priceTableDelete');
$router->get('/admin/finance/discount-rules',                       'Modulos/financeiro/FinanceController@discountRules');
$router->post('/admin/finance/discount-rules',                      'Modulos/financeiro/FinanceController@discountRuleStore');
$router->post('/admin/finance/discount-rules/{id}/toggle',          'Modulos/financeiro/FinanceController@discountRuleToggle');
$router->get('/admin/finance/report/inadimplencia',                 'Modulos/financeiro/FinanceController@reportInadimplencia');
$router->get('/admin/alunos/search',                                 'Modulos/financeiro/FinanceController@alunoSearch');
$router->get('/admin/alunos/{alunoId}/responsaveis',                 'Modulos/financeiro/FinanceController@alunoResponsaveis');
$router->get('/admin/finance/plans',                                 'Modulos/financeiro/FinanceController@plansIndex');
$router->get('/admin/finance/plans/create',                          'Modulos/financeiro/FinanceController@planCreate');
$router->post('/admin/finance/plans',                                'Modulos/financeiro/FinanceController@planStore');
$router->get('/admin/finance/plans/{id}',                            'Modulos/financeiro/FinanceController@planShow');
$router->post('/admin/finance/plans/{id}/toggle',                    'Modulos/financeiro/FinanceController@planToggle');
$router->post('/admin/finance/plans/{planId}/items',                 'Modulos/financeiro/FinanceController@planItemStore');
$router->get('/admin/finance/plans/{planId}/items/{itemId}/dados',   'Modulos/financeiro/FinanceController@planItemDados');
$router->post('/admin/finance/plans/{planId}/items/{itemId}/update',  'Modulos/financeiro/FinanceController@planItemUpdate');
$router->post('/admin/finance/plans/{planId}/items/{itemId}/delete', 'Modulos/financeiro/FinanceController@planItemDelete');
$router->get('/admin/finance/aluno/{alunoId}/extrato',               'Modulos/financeiro/FinanceController@alunoExtrato');
$router->post('/admin/finance/ledger/{ledgerId}/estorno',            'Modulos/financeiro/FinanceController@ledgerEstornar');
$router->get('/admin/finance/aluno/{alunoId}/resumo',               'Modulos/financeiro/FinanceController@alunoResumoJson');
$router->get('/admin/finance/aluno/{alunoId}/parcelas-vencidas',    'Modulos/financeiro/FinanceController@alunoParcelasVencidas');
$router->get('/admin/finance/charges',                               'Modulos/financeiro/FinanceController@chargesList');
$router->post('/admin/finance/charges/{chargeId}/pay',               'Modulos/financeiro/FinanceController@chargePay');
$router->get('/admin/finance/aluno/{alunoId}/charge',                'Modulos/financeiro/FinanceController@chargeCreate');
$router->post('/admin/finance/aluno/{alunoId}/charge',               'Modulos/financeiro/FinanceController@chargeStore');
$router->get('/admin/finance/charges/batch',                         'Modulos/financeiro/FinanceController@chargeBatch');
$router->post('/admin/finance/charges/batch',                        'Modulos/financeiro/FinanceController@chargeBatchStore');
$router->get('/admin/finance/cashflow',                              'Modulos/financeiro/FinanceReportController@cashFlow');
$router->get('/admin/finance/bills',                                 'Modulos/financeiro/FinanceReportController@billsIndex');
$router->post('/admin/finance/bills',                                'Modulos/financeiro/FinanceReportController@billStore');
$router->post('/admin/finance/bills/{id}/pay',                       'Modulos/financeiro/FinanceReportController@billPay');
$router->post('/admin/finance/bills/{id}/delete',                    'Modulos/financeiro/FinanceReportController@billDelete');
$router->get('/admin/finance/reports/dre',                           'Modulos/financeiro/FinanceReportController@dre');
$router->get('/admin/finance/reports/dfc',                           'Modulos/financeiro/FinanceReportController@dfc');
$router->get('/admin/finance/reports/balanco',                       'Modulos/financeiro/FinanceReportController@balanco');
$router->get('/admin/finance/reports/dmpl',                          'Modulos/financeiro/FinanceReportController@dmpl');
$router->get('/admin/finance/reports/dlpa',                          'Modulos/financeiro/FinanceReportController@dlpa');
$router->get('/admin/finance/settings',                              'Modulos/financeiro/FinanceController@settings');
$router->post('/admin/finance/settings',                             'Modulos/financeiro/FinanceController@settingsSave');
$router->get('/admin/finance/contracts/{contractId}/renegotiation',  'Modulos/financeiro/FinanceController@renegotiationCreate');
$router->post('/admin/finance/contracts/{contractId}/renegotiation', 'Modulos/financeiro/FinanceController@renegotiationStore');
