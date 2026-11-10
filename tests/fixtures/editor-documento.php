<?php
/** HTML real do editor com um modelo fictício; endpoints são simulados pelo Playwright. */
$raiz = dirname(__DIR__, 2);
define('BASE_PATH', $raiz . '/backend');
define('URL', 'http://editor.localhost');
require BASE_PATH . '/vendor/autoload.php';

use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;

if (in_array('--pdf', $argv, true)) {
    $pdf = new \Dompdf\Dompdf();
    $pdf->loadHtml('<h1>Documento de teste</h1><p>Resultado da impressão.</p>');
    $pdf->render();
    echo $pdf->output();
    exit;
}

$modelo = ['id' => 21, 'nome' => 'Documento de teste', 'codigo' => 'documento_teste', 'usar_layout_padrao' => 0];
$estrutura = ModeloDocumentoService::estruturaVazia('a4', 'paisagem', 8);
$estrutura['body']['sections'] = [ModeloDocumentoService::secaoPadrao([100])];
$catalogo = ModeloDocumentoService::catalogoElementosEditor();
$csrf_token = 'csrf-teste-local';
$vars_preview = ['aluno_nome' => 'Aluno de demonstração'];
// A identidade visual da escola depende do banco; o restante da view é o arquivo de produção.
$view = file_get_contents(BASE_PATH . '/app/Modulos/modelos-documentos/Views/admin/editor.php');
$view = str_replace('<?= \\App\\Core\\LayoutHelper::generateCustomCSS() ?>', '', $view);
eval('?>' . $view);
