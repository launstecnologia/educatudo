<?php
/**
 * EducaTudo - AdminDeclarationController
 * Gera declarações oficiais do aluno (matrícula, frequência, comparecimento,
 * transferência) em PDF, usando os dados institucionais da unidade no cabeçalho.
 */

require_once __DIR__ . '/../Admin/AdminBaseController.php';
require_once __DIR__ . '/../../Core/LayoutHelper.php';
require_once __DIR__ . '/../../Services/DeclarationService.php';

use App\Services\DeclarationService;

if (!class_exists('AdminDeclarationController')) {
class AdminDeclarationController extends AdminBaseController
{
    public function gerarPdf($id, $tipo): void
    {
        if (!$this->enforceAdminPermissionKey('declaracoes_aluno', 'visualizar', false)) {
            return;
        }

        $alunoId = (int) $id;
        $tipo = strtolower(trim((string) $tipo));
        if ($alunoId <= 0 || !DeclarationService::isTipoValido($tipo)) {
            $_SESSION['error_message'] = 'Declaração inválida';
            $this->redirect('/admin/students');
            return;
        }

        $service = new DeclarationService($this->db);
        $aluno = $service->getAluno($alunoId);
        if (!$aluno) {
            $_SESSION['error_message'] = 'Aluno não encontrado';
            $this->redirect('/admin/students');
            return;
        }

        $unidade = $service->getUnidadeForAluno($aluno);
        $hoje = date('Y-m-d');

        // Período (usado em frequência). Default: 1º de janeiro do ano atual até hoje.
        $inicio = $this->sanitizeDate($_GET['inicio'] ?? '') ?: (date('Y') . '-01-01');
        $fim = $this->sanitizeDate($_GET['fim'] ?? '') ?: $hoje;
        if ($inicio > $fim) {
            [$inicio, $fim] = [$fim, $inicio];
        }
        // Data específica (comparecimento). Default: hoje.
        $dataComparecimento = $this->sanitizeDate($_GET['data'] ?? '') ?: $hoje;
        $periodoTexto = trim((string) ($_GET['periodo'] ?? ''));

        // Parâmetros livres usados pelas autorizações (texto sanitizado).
        $autParams = [
            'nome_autorizado' => $this->sanitizeText($_GET['nome_autorizado'] ?? ''),
            'documento' => $this->sanitizeText($_GET['documento'] ?? ''),
            'parentesco' => $this->sanitizeText($_GET['parentesco'] ?? ''),
            'motivo' => $this->sanitizeText($_GET['motivo'] ?? ''),
            'horario' => $this->sanitizeText($_GET['horario'] ?? ''),
            'local' => $this->sanitizeText($_GET['local'] ?? ''),
            'hora_saida' => $this->sanitizeText($_GET['hora_saida'] ?? ''),
            'hora_retorno' => $this->sanitizeText($_GET['hora_retorno'] ?? ''),
            'finalidade' => $this->sanitizeText($_GET['finalidade'] ?? ''),
        ];

        $dados = [
            'aluno' => $aluno,
            'unidade' => $unidade,
            'matricula' => $service->getMatriculaAtiva($alunoId),
        ];

        if ($tipo === 'frequencia') {
            $dados['frequencia'] = $service->getFrequencia($alunoId, $inicio, $fim);
            $dados['periodo'] = ['inicio' => $inicio, 'fim' => $fim];
        } elseif ($tipo === 'comparecimento') {
            $dados['data_comparecimento'] = $dataComparecimento;
            $dados['periodo_texto'] = $periodoTexto;
        } elseif ($tipo === 'transferencia') {
            $dados['matricula_encerrada'] = $service->getMatriculaEncerrada($alunoId) ?: $dados['matricula'];
        } elseif ($tipo === 'historico') {
            $dados['historico'] = $service->getHistorico($alunoId);
        } elseif ($tipo === 'ficha_matricula') {
            $dados['responsaveis'] = $service->getResponsaveis($alunoId);
        } elseif (in_array($tipo, DeclarationService::TIPOS_AUTORIZACOES, true)) {
            $dados['responsaveis'] = $service->getResponsaveis($alunoId);
            $dados['aut'] = $autParams;
            $dados['data_evento'] = $dataComparecimento;
        }

        $user = $this->auth->getUser();
        $numero = $service->registrarEmissao(
            $alunoId,
            isset($unidade['id']) ? (int) $unidade['id'] : null,
            $tipo,
            ['inicio' => $inicio, 'fim' => $fim, 'data' => $dataComparecimento, 'periodo' => $periodoTexto] + $autParams,
            isset($user['id']) ? (int) $user['id'] : null,
            $user['nome'] ?? null
        );

        $viewData = [
            'tipo' => $tipo,
            'titulo' => DeclarationService::TIPO_LABELS[$tipo] ?? 'Documento',
            'dados' => $dados,
            'logo_data' => $this->resolveLogo($unidade),
            'numero' => $numero,
            'ano' => (int) date('Y'),
            'gerado_em' => date('d/m/Y'),
            'cidade_data' => $this->cidadeData($unidade),
        ];

        try {
            if (!class_exists('Logger')) {
                require_once __DIR__ . '/../../Core/Logger.php';
            }
            \Logger::logAudit(
                'GENERATE_DECLARATION',
                '/admin/students/' . $alunoId . '/declaracoes/' . $tipo . '/pdf',
                ['aluno_id' => $alunoId, 'tipo' => $tipo, 'numero' => $numero],
                isset($user['id']) ? (int) $user['id'] : null,
                $user['tipo'] ?? null
            );
        } catch (\Throwable $e) {
            // auditoria é best-effort
        }

        $html = $this->renderTemplate($tipo, $viewData);
        $slug = $this->slug((string) ($aluno['nome'] ?? 'aluno'), $alunoId);
        $prefixo = in_array($tipo, DeclarationService::TIPOS, true) ? 'declaracao' : 'documento';
        $filename = $prefixo . '_' . $tipo . '_' . $slug . '_' . date('Ymd_His') . '.pdf';
        $this->outputPdf($html, $filename, DeclarationService::isLandscape($tipo) ? 'landscape' : 'portrait');
    }

    private function renderTemplate(string $tipo, array $viewData): string
    {
        $templateFile = __DIR__ . '/../../Views/admin/declarations/templates/' . $tipo . '.php';
        if (!is_file($templateFile)) {
            $templateFile = __DIR__ . '/../../Views/admin/declarations/templates/matricula.php';
        }
        ob_start();
        extract($viewData, EXTR_SKIP);
        require $templateFile;
        return (string) ob_get_clean();
    }

    private function outputPdf(string $html, string $filename, string $orientation = 'portrait'): void
    {
        $orientation = $orientation === 'landscape' ? 'landscape' : 'portrait';
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $dompdf->output();
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    /**
     * Prioriza o logo da unidade; cai no logo da escola se a unidade não tiver.
     *
     * @param array<string, mixed>|null $unidade
     */
    private function resolveLogo(?array $unidade): string
    {
        $unidadeLogo = trim((string) ($unidade['logo_url'] ?? ''));
        if ($unidadeLogo !== '') {
            return $unidadeLogo;
        }
        return $this->resolveSchoolLogoForPdf();
    }

    private function resolveSchoolLogoForPdf(): string
    {
        try {
            $url = (string) LayoutHelper::getNavbarLogoUrl();
            if ($url === '') {
                return '';
            }
            $parts = parse_url($url) ?: [];
            $query = [];
            if (!empty($parts['query'])) {
                parse_str((string) $parts['query'], $query);
            }
            $filePath = '';
            $key = isset($query['key']) ? (string) $query['key'] : '';
            $type = isset($query['type']) ? (string) $query['type'] : 'layout';
            if ($key !== '') {
                require_once __DIR__ . '/../../Services/MediaStorageService.php';
                $media = new MediaStorageService($this->config);
                $localPath = $media->getLocalPath($type, $key);
                if ($localPath !== null && is_file($localPath) && is_readable($localPath)) {
                    $filePath = $localPath;
                }
            }
            if ($filePath === '' && !empty($parts['path'])) {
                $relative = ltrim((string) $parts['path'], '/');
                foreach ([__DIR__ . '/../../../public/' . $relative, __DIR__ . '/../../../' . $relative] as $cand) {
                    if (is_file($cand) && is_readable($cand)) {
                        $filePath = $cand;
                        break;
                    }
                }
            }
            if ($filePath === '') {
                return '';
            }
            $bin = @file_get_contents($filePath);
            if (!is_string($bin) || $bin === '') {
                return '';
            }
            $ext = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeMap = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
            $mime = $mimeMap[$ext] ?? 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode($bin);
        } catch (\Throwable $e) {
            error_log('AdminDeclarationController resolveSchoolLogoForPdf: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Linha "Cidade, dd de mês de aaaa" para o fecho da declaração.
     *
     * @param array<string, mixed>|null $unidade
     */
    private function cidadeData(?array $unidade): string
    {
        $cidade = trim((string) ($unidade['cidade'] ?? ''));
        $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $data = (int) date('j') . ' de ' . ($meses[(int) date('n')] ?? '') . ' de ' . date('Y');
        return ($cidade !== '' ? $cidade . ', ' : '') . $data;
    }

    private function sanitizeText($raw): string
    {
        $txt = trim((string) $raw);
        if ($txt === '') {
            return '';
        }
        $txt = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $txt) ?? '';
        return mb_substr($txt, 0, 180);
    }

    private function sanitizeDate($raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $raw);
        if ($dt && $dt->format('Y-m-d') === $raw) {
            return $raw;
        }
        return '';
    }

    private function slug(string $nome, int $alunoId): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $nome);
        $slug = trim((string) $slug, '_-');
        return $slug !== '' ? $slug : ('aluno_' . $alunoId);
    }
}
}
