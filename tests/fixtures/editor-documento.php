<?php
/** HTML real do editor com um modelo fictício; endpoints são simulados pelo Playwright. */
$raiz = dirname(__DIR__, 2);
define('BASE_PATH', $raiz . '/backend');
define('URL', 'http://editor.localhost');
require BASE_PATH . '/vendor/autoload.php';

use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;
use App\Modulos\ModelosDocumentos\Services\AssistenteDocumentoService;

$imagemTeste = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=';
$fotoTeste = '<img src="' . $imagemTeste . '" alt="Foto do aluno" style="width:25mm;height:30mm;">';

if (in_array('--assistente', $argv, true)) {
    echo json_encode(AssistenteDocumentoService::interpretarResposta(file_get_contents('php://stdin')), JSON_THROW_ON_ERROR);
    exit;
}

if (in_array('--reproducao', $argv, true)) {
    echo json_encode(\App\Modulos\ModelosDocumentos\Services\ReprodutorLayoutDocumentoService::estruturaDaResposta(file_get_contents('php://stdin')), JSON_THROW_ON_ERROR);
    exit;
}

if (in_array('--impressao-ajustada', $argv, true)) {
    $estrutura = json_decode(file_get_contents('php://stdin'), true, 512, JSON_THROW_ON_ERROR);
    $servico = new class extends ModeloDocumentoService {
        public function __construct() {}
        public function aplicarLayoutPadraoNoModelo(array $modelo): array { return $modelo; }
    };
    echo $servico->renderHtml(
        ['codigo' => 'teste_ajuste', 'estrutura_json' => json_encode($estrutura)],
        ['assinante_nome' => 'Direção', 'assinante_cargo' => 'Direção', 'logo_html' => '<span></span>', 'aluno_foto_html' => $fotoTeste],
        'simples'
    );
    exit;
}

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
$ia_disponivel = true;
$assistente_disponivel = true;
$assistente_ia_disponivel = !in_array('--sem-ia-chat', $argv, true);
$placeholders = ModeloDocumentoService::PLACEHOLDERS;
$grupos_placeholders = ModeloDocumentoService::gruposPlaceholders();
$mascaras_campo = ModeloDocumentoService::MASCARAS_CAMPO;
$vars_preview = ['aluno_nome' => 'Aluno de demonstração', 'quadro_notas_html' => '<table><thead><tr><th>Matéria</th><th>Nota</th></tr></thead><tbody><tr data-boletim-materia="17"><td>Matemática</td><td data-boletim-coluna="b1_pp">7,5</td></tr></tbody></table>'];
$vars_preview['aut_local'] = 'Unidade I';
$vars_preview['aluno_foto_html'] = $fotoTeste;
$vars_preview['logo_html'] = '{{logo_html}}';
$logo_preview = $imagemTeste;
$vars_preview = array_merge($vars_preview, \App\Modulos\ModelosDocumentos\Services\QuadroBoletimEditavelService::variaveis($vars_preview['quadro_notas_html']));
// A identidade visual da escola depende do banco; o restante da view é o arquivo de produção.
$view = file_get_contents(BASE_PATH . '/app/Modulos/modelos-documentos/Views/admin/editor.php');
$view = str_replace('<?= \\App\\Core\\LayoutHelper::generateCustomCSS() ?>', '', $view);
$view = str_replace("__DIR__ . '/_campos-editor.php'", "BASE_PATH . '/app/Modulos/modelos-documentos/Views/admin/_campos-editor.php'", $view);
eval('?>' . $view);
