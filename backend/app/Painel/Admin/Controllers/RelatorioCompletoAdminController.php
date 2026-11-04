<?php

namespace App\Painel\Admin\Controllers;

use App\Core\Logger;
use App\Painel\Admin\Services\RelatorioCompletoAcervo;
use App\Painel\Admin\Services\RelatorioCompletoArquivo;
use App\Painel\Admin\Services\RelatorioCompletoService;

if (!class_exists(__NAMESPACE__ . '\\RelatorioCompletoAdminController', false)) {
class RelatorioCompletoAdminController extends AdminBaseController
{
    public function index(): void
    {
        $this->exigirPermissao();
        $this->exibir();
    }

    public function gerar(): void
    {
        $this->exigirPermissao();
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->exibir('Sessão expirada. Atualize a página e tente de novo.', 'error');
            return;
        }

        $formato = (string) ($_POST['formato'] ?? '');
        if ($formato !== 'excel' && $formato !== 'pdf') {
            $this->exibir('Escolha Excel ou PDF.', 'error');
            return;
        }

        $service = new RelatorioCompletoService($this->db);
        $acervo = new RelatorioCompletoAcervo($this->db);
        try {
            $filtros = $service->normalizar($_POST);
            $recente = $acervo->identicoRecente($filtros, $formato);
            if ($recente !== null) {
                $quando = $recente['quando'] !== '' ? $recente['quando'] : 'há menos de 1 hora';
                $this->exibir(
                    'Este mesmo relatório já foi gerado em ' . $quando . '. Baixe a versão guardada em Documentos. Um relatório idêntico só pode ser gerado outra vez depois de 1 hora.',
                    'error',
                    $recente
                );
                return;
            }
            $relatorio = $service->montar($_POST);
            $arquivo = new RelatorioCompletoArquivo();
            if ($formato === 'pdf') {
                $bin = $arquivo->pdf($relatorio);
                $nome = 'relatorio-completo-' . date('Ymd-His') . '.pdf';
                $tipo = 'application/pdf';
            } else {
                $bin = $arquivo->excel($relatorio);
                $nome = 'relatorio-completo-' . date('Ymd-His') . '.xlsx';
                $tipo = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }
            $acervo->guardar(
                $filtros,
                $formato,
                $bin,
                (int) ($relatorio['total_alunos'] ?? 0),
                $service->catalogo(),
                $this->auth->getUser() ?: null,
                is_array($this->config) ? $this->config : []
            );
        } catch (\RuntimeException $e) {
            $this->exibir($e->getMessage(), 'error');
            return;
        } catch (\Throwable $e) {
            Logger::error('Falha ao gerar relatório completo', ['exception' => $e], 'general');
            $this->exibir('Não foi possível gerar o relatório. Tente uma turma menor ou menos bancos.', 'error');
            return;
        }

        $this->enviar($bin, $nome, $tipo);
    }

    private function enviar(string $bin, string $nome, string $tipo): void
    {
        $nome = str_replace(['"', "\r", "\n"], '', $nome);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: no-store');
        echo $bin;
        exit;
    }

    public function baixar(int $id): void
    {
        $this->exigirPermissao();
        $arquivo = (new RelatorioCompletoAcervo($this->db))->baixar($id, is_array($this->config) ? $this->config : []);
        if ($arquivo === null) {
            $this->exibir('Esta versão não está mais em Documentos.', 'error');
            return;
        }
        $this->enviar($arquivo['binario'], $arquivo['nome'], $arquivo['mime']);
    }

    private function exigirPermissao(): void
    {
        $this->enforceAdminPermissionKey('relatorios_gerais', 'visualizar', false);
    }

    /**
     * @param array{id:int,quando:string,formato:string}|null $versaoRecente
     */
    private function exibir(string $mensagem = '', string $tipo = '', ?array $versaoRecente = null): void
    {
        $service = new RelatorioCompletoService($this->db);
        $opcoes = $service->opcoes();
        $enviado = (($_POST['formulario'] ?? '') === '1');
        $marcados = $enviado
            ? (is_array($_POST['bancos'] ?? null) ? array_map('strval', $_POST['bancos']) : [])
            : ['dados', 'notas', 'faltas_resumo', 'faltas', 'ocorrencias'];

        $this->viewWithLayout('admin', 'admin/relatorios/completo', [
            'title' => 'Relatório completo - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'relatorio_completo',
            'csrf_token' => $this->generateCsrf(),
            'catalogo' => $service->catalogo(),
            'anos' => $opcoes['anos'],
            'turmas' => $opcoes['turmas'],
            'filtros' => [
                'ano' => (int) ($_POST['ano'] ?? 0),
                'turma_id' => (int) ($_POST['turma_id'] ?? 0),
                'busca' => trim((string) ($_POST['busca'] ?? '')),
                'data_inicio' => trim((string) ($_POST['data_inicio'] ?? '')),
                'data_fim' => trim((string) ($_POST['data_fim'] ?? '')),
                'incluir_inativos' => $enviado && !empty($_POST['incluir_inativos']),
            ],
            'bancos_marcados' => $marcados,
            'flash_status' => $tipo === 'error' ? 'error' : '',
            'flash_message' => $mensagem,
            'versao_recente' => $versaoRecente,
        ]);
    }
}
}
