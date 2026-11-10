<?php
/** View real com dados fictícios; não acessa uma escola. */
define('BASE_PATH', dirname(__DIR__, 2) . '/backend');
define('URL', 'http://editor.localhost');
require BASE_PATH . '/vendor/autoload.php';
$executar = true;
$fonte = 'vida_escolar';
$ano_letivo = 2024;
$anos_letivos = [2024, 2025];
$nota_abaixo_de = null;
$modelo_documento_id = 21;
$modelos_boletim_documento = [['id' => 21, 'nome' => 'Boletim do Ensino Médio'], ['id' => 22, 'nome' => 'Boletim alternativo']];
$relatorio = ['fonte' => 'vida_escolar', 'total_alunos' => 1, 'alunos_com_ficha' => 1, 'ano_letivo' => 2024, 'grupos' => [['alunos' => [['id' => 8, 'nome' => 'Aluna de teste', 'turma' => '1 A', 'ficha_id' => 17]]]]];
$view = file_get_contents(BASE_PATH . '/app/Painel/Admin/Views/reports/boletim_coordenacao.php');
$view = str_replace("\\App\\Core\\LayoutHelper::isModuleEnabled('eventos_avaliativos')", 'true', $view);
echo '<!doctype html><html><head><meta charset="utf-8"><style>.hidden{display:none}</style></head><body>';
eval('?>' . $view);
echo '</body></html>';
