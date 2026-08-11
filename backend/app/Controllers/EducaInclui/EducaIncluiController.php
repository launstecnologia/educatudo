<?php
/**
 * EducaTudo — EducaInclui
 * Gestão da Máscara de Acessibilidade (Coordenação/AEE).
 */

require_once __DIR__ . '/../Admin/AdminBaseController.php';
require_once __DIR__ . '/../../Helpers/CatalogoRegraMascara.php';
require_once __DIR__ . '/../../Services/EducaIncluiService.php';
require_once __DIR__ . '/../../Services/MascaraResolver.php';
require_once __DIR__ . '/../../Models/EducaInclui/MascaraAluno.php';
require_once __DIR__ . '/../../Models/EducaInclui/RegraMascara.php';
require_once __DIR__ . '/../../Models/EducaInclui/LaudoAluno.php';
require_once __DIR__ . '/../../Models/EducaInclui/VersaoAdaptadaLog.php';
require_once __DIR__ . '/../../Models/EducaInclui/VersaoAdaptada.php';

if (!class_exists('EducaIncluiController')) {
class EducaIncluiController extends AdminBaseController
{
    private $service;
    private $accommodations;
    private $rules;
    private $documents;
    private $auditLog;
    private $versions;

    public function __construct()
    {
        parent::__construct();
        $this->service = new EducaIncluiService();
        $this->accommodations = new MascaraAluno();
        $this->rules = new RegraMascara();
        $this->documents = new LaudoAluno();
        $this->auditLog = new VersaoAdaptadaLog();
        $this->versions = new VersaoAdaptada();
    }

    /**
     * Fila de aprovação das versões adaptadas (clones) geradas para alunos.
     */
    public function versoes(): void
    {
        if (!$this->enforceAdminPermissionKey('inclusao', 'visualizar', false)) {
            return;
        }
        $pendentes = $this->versions->listPending();
        $aprovadas = $this->versions->listApproved();
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/inclusao/versoes', [
            'title' => 'EducaInclui - Versões adaptadas',
            'page_title' => 'EducaInclui',
            'current_page' => 'inclusao',
            'user' => $this->auth->getUser(),
            'pendentes' => $pendentes,
            'aprovadas' => $aprovadas,
            'flash' => $flash,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Gera sob demanda as versões adaptadas que faltam, para provas já aprovadas
     * dos alunos com máscara significativa. Útil quando a prova já foi aprovada e
     * não há como reprovar para disparar a geração automática.
     */
    public function gerarPendentes(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'alterar', false)) {
            return;
        }

        require_once __DIR__ . '/../../Services/MascaraResolver.php';
        require_once __DIR__ . '/../../Services/AssessmentVersionGenerator.php';
        require_once __DIR__ . '/../../Services/AIJobService.php';

        $geradas = 0;
        $reescritas = 0;
        $reescritaErros = 0;
        $alunosComMascara = 0;
        try {
            $masks = $this->db->fetchAll(
                "SELECT id, aluno_id, status FROM mascaras_alunos
                 WHERE tipo_adaptacao = 'significativa'
                   AND status IN ('ativa', 'rascunho')
                   AND (data_inicio IS NULL OR data_inicio <= CURDATE())
                   AND (data_fim IS NULL OR data_fim >= CURDATE())"
            );
            $gen = new \AssessmentVersionGenerator();
            foreach ($masks as $m) {
                $mascaraId = (int) ($m['id'] ?? 0);
                $alunoId = (int) ($m['aluno_id'] ?? 0);
                if ($mascaraId <= 0 || $alunoId <= 0) {
                    continue;
                }
                $rules = $this->rules->getMapaPorMascara($mascaraId);
                $rules = array_intersect_key($rules, array_flip(\CatalogoRegraMascara::validKeys()));
                if (!\MascaraResolver::requiresClone($rules)) {
                    continue;
                }
                $alunoRow = $this->db->fetch("SELECT turma_id FROM alunos WHERE id = :id", ['id' => $alunoId]);
                $turma = (int) ($alunoRow['turma_id'] ?? 0);
                if ($turma <= 0) {
                    continue;
                }
                $alunosComMascara++;

                // Provas que o aluno deveria fazer (avulsa pela turma OU dentro de bloco
                // cujas turmas incluem a do aluno), excluindo clones já existentes.
                $candidatas = $this->db->fetchAll(
                    "SELECT DISTINCT p.id
                     FROM provas p
                     LEFT JOIN provas_turmas pt ON pt.prova_id = p.id
                     LEFT JOIN provas_blocos_vinculo pbv ON pbv.prova_id = p.id
                     LEFT JOIN provas_blocos pb ON pb.id = pbv.bloco_id AND pb.deleted_at IS NULL
                     LEFT JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pbv.bloco_id
                     WHERE p.deleted_at IS NULL
                       AND p.liberada = 1
                       AND p.ativo = 1
                       AND (p.data_fim IS NULL OR p.data_fim >= NOW())
                       AND NOT EXISTS (SELECT 1 FROM versoes_adaptadas av WHERE av.adapted_prova_id = p.id)
                       AND (
                           p.turma_id = :t1
                           OR pt.turma_id = :t2
                           OR pbt.turma_id = :t3
                           OR pb.turma_id = :t4
                           OR (
                               p.turma_id IS NULL
                               AND NOT EXISTS (SELECT 1 FROM provas_turmas pt_all WHERE pt_all.prova_id = p.id)
                               AND NOT EXISTS (
                                   SELECT 1
                                   FROM provas_blocos_vinculo pbv_all
                                   JOIN provas_blocos_turmas pbt_all ON pbt_all.bloco_id = pbv_all.bloco_id
                                   WHERE pbv_all.prova_id = p.id
                               )
                           )
                       )",
                    ['t1' => $turma, 't2' => $turma, 't3' => $turma, 't4' => $turma]
                );
                foreach ($candidatas as $c) {
                    $pid = (int) ($c['id'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    $ver = $this->versions->getAnyFor($pid, $alunoId);
                    if (!$ver) {
                        $ver = $gen->ensureDraft($pid, $alunoId, $mascaraId, $rules);
                        if ($ver) {
                            $geradas++;
                        }
                    }
                    if ($ver && \CatalogoRegraMascara::hasAiRewriteRule($rules)) {
                        try {
                            if ($this->processarReescritaVersaoImediata($ver, $rules, $alunoId)) {
                                $reescritas++;
                            }
                        } catch (\Throwable $eRewrite) {
                            $reescritaErros++;
                            error_log('EducaIncluiController::gerarPendentes reescrita: ' . $eRewrite->getMessage());
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('EducaIncluiController::gerarPendentes: ' . $e->getMessage());
            $this->setFlashMessage('Erro ao gerar versões: ' . $e->getMessage(), 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }

        if ($geradas > 0) {
            $msg = $geradas . ($geradas > 1 ? ' versões adaptadas foram geradas' : ' versão adaptada foi gerada');
            if ($reescritas > 0) {
                $msg .= ' e ' . $reescritas . ($reescritas > 1 ? ' versões foram reescritas por IA' : ' versão foi reescrita por IA');
            } elseif ($reescritaErros > 0) {
                $msg .= '. Algumas reescritas por IA não puderam ser processadas agora; o cron/job pode tentar depois.';
            }
            $this->setFlashMessage($msg . '.', 'success');
        } elseif ($alunosComMascara === 0) {
            $this->setFlashMessage('Nenhum aluno com máscara significativa ativa ou em rascunho que exija prova clonada.', 'error');
        } elseif ($reescritas > 0) {
            $this->setFlashMessage(
                $reescritas . ($reescritas > 1 ? ' versões existentes foram reescritas por IA.' : ' versão existente foi reescrita por IA.'),
                'success'
            );
        } elseif ($reescritaErros > 0) {
            $this->setFlashMessage('Nenhuma versão nova a gerar. Algumas reescritas por IA não puderam ser processadas agora; verifique a configuração da IA ou tente novamente.', 'error');
        } else {
            $this->setFlashMessage('Nenhuma versão nova a gerar (já existem ou nenhuma prova ativa/futura corresponde às turmas dos alunos).', 'error');
        }
        $this->redirect('/admin/inclusao/versoes');
    }

    /**
     * Tenta reescrever imediatamente uma versão adaptada que ainda não possui diff IA.
     *
     * @param array<string,mixed> $ver
     * @param array<string,string> $rules
     */
    private function processarReescritaVersaoImediata(array $ver, array $rules, int $alunoId): bool
    {
        $versionId = (int) ($ver['id'] ?? 0);
        $adaptedProvaId = (int) ($ver['adapted_prova_id'] ?? 0);
        if ($versionId <= 0 || $adaptedProvaId <= 0) {
            return false;
        }
        if ($this->auditLog->latestDetailsByVersionAction($versionId, 'versao_reescrita_ia')) {
            return false;
        }
        \App\Services\AIJobService::rewriteAssessmentVersionNow([
            'version_id' => $versionId,
            'adapted_prova_id' => $adaptedProvaId,
            'mascara_id' => (int) ($ver['mascara_id'] ?? 0),
            'aluno_id' => $alunoId,
            'prova_id' => (int) ($ver['prova_id'] ?? 0),
            'reduce_options_keep' => \MascaraResolver::reduceOptionsKeep($rules),
            'modes' => [
                'simplified_instructions' => !empty($rules['simplified_instructions']),
                'shorten_statement' => !empty($rules['shorten_statement']),
                'literal_language' => !empty($rules['literal_language']),
            ],
        ]);
        return true;
    }

    /**
     * Diff visual entre a prova original e a versão adaptada (clone).
     */
    public function verVersao(int $versionId): void
    {
        if (!$this->enforceAdminPermissionKey('inclusao', 'visualizar', false)) {
            return;
        }
        $ver = $this->versions->getById($versionId);
        if (!$ver) {
            $this->setFlashMessage('Versão não encontrada.', 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }

        require_once __DIR__ . '/../../Models/Exams/Exam.php';
        $exam = new \Exam();

        $originalId = (int) $ver['prova_id'];
        $adaptedId = (int) ($ver['adapted_prova_id'] ?? 0);

        $provaOriginal = $exam->findById($originalId);
        $provaAdaptada = $adaptedId > 0 ? $exam->findById($adaptedId) : null;

        $qOriginais = $exam->getQuestoes($originalId);
        $qAdaptadas = $adaptedId > 0 ? $exam->getQuestoes($adaptedId) : [];
        $qOriginais = $this->hidratarQuestoesComAlternativas($exam, $qOriginais);
        $qAdaptadas = $this->hidratarQuestoesComAlternativas($exam, $qAdaptadas);

        // Casa por enunciado normalizado (o clone copia o enunciado verbatim).
        $norm = static function ($s): string {
            return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s)));
        };
        $adaptByEnun = [];
        foreach ($qAdaptadas as $qa) {
            $adaptByEnun[$norm($qa['enunciado'] ?? '')] = $qa;
        }

        $linhas = [];
        foreach ($qOriginais as $idx => $qo) {
            $key = $norm($qo['enunciado'] ?? '');
            $mantida = isset($adaptByEnun[$key]);
            $linhas[] = [
                'numero' => $idx + 1,
                'enunciado' => $qo['enunciado'] ?? '',
                'tipo' => $qo['tipo'] ?? '',
                'valor_original' => (float) ($qo['valor'] ?? 0),
                'valor_adaptado' => $mantida ? (float) ($adaptByEnun[$key]['valor'] ?? 0) : null,
                'mantida' => $mantida,
            ];
        }

        $rulesSnapshot = [];
        if (!empty($ver['regras_snapshot_json'])) {
            $decoded = json_decode((string) $ver['regras_snapshot_json'], true);
            if (is_array($decoded)) {
                $rulesSnapshot = $decoded;
            }
        }

        $aluno = $this->db->fetch("SELECT nome FROM alunos WHERE id = :id", ['id' => (int) $ver['aluno_id']]);

        // Reescrita por IA (se houver): antes/depois por questão.
        $reescritaIa = $this->auditLog->latestDetailsByVersionAction($versionId, 'versao_reescrita_ia');
        $reescritaMapa = is_array($reescritaIa['mapa'] ?? null) ? $reescritaIa['mapa'] : [];
        $reescritaMapa = $this->mesclarReescritasIndividuais($versionId, $reescritaMapa);
        if (!empty($reescritaMapa)) {
            $reescritaMapa = $this->hidratarMapaReescrita($reescritaMapa, $qOriginais, $qAdaptadas);
        }

        $this->viewWithLayout('admin', 'admin/inclusao/versao-diff', [
            'title' => 'EducaInclui - Diff da versão',
            'page_title' => 'EducaInclui',
            'current_page' => 'inclusao',
            'user' => $this->auth->getUser(),
            'versao' => $ver,
            'aluno_nome' => $aluno['nome'] ?? ('Aluno #' . (int) $ver['aluno_id']),
            'prova_original' => $provaOriginal,
            'prova_adaptada' => $provaAdaptada,
            'linhas' => $linhas,
            'total_original' => count($qOriginais),
            'total_adaptada' => count($qAdaptadas),
            'rules_snapshot' => $rulesSnapshot,
            'reescrita_mapa' => $reescritaMapa,
            'flash' => $this->getFlashMessage(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * PDF da prova adaptada para impressão/aplicação ao aluno.
     */
    public function pdfVersao(int $versionId): void
    {
        if (!$this->enforceAdminPermissionKey('inclusao', 'visualizar', false)) {
            return;
        }
        $ver = $this->versions->getById($versionId);
        if (!$ver) {
            $this->setFlashMessage('Versão não encontrada.', 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }

        require_once __DIR__ . '/../../Models/Exams/Exam.php';
        require_once __DIR__ . '/../../Core/LayoutHelper.php';
        if (!class_exists('\Dompdf\Dompdf')) {
            require_once __DIR__ . '/../../../vendor/autoload.php';
        }

        $exam = new \Exam();
        $provaId = (int) ($ver['adapted_prova_id'] ?? 0);
        if ($provaId <= 0) {
            $provaId = (int) ($ver['prova_id'] ?? 0);
        }
        $prova = $exam->findById($provaId);
        if (!$prova) {
            $this->setFlashMessage('Prova adaptada não encontrada.', 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }

        $questoes = $this->hidratarQuestoesComAlternativas($exam, $exam->getQuestoes($provaId));
        $aluno = $this->db->fetch(
            "SELECT a.nome, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id",
            ['id' => (int) $ver['aluno_id']]
        ) ?: [];

        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');

        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $html = $this->renderProvaAdaptadaPdfHtml($prova, $questoes, $aluno, $this->resolveLogoDataUri());

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $filename = 'prova-adaptada-' . $versionId . '-' . date('Ymd_His') . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $dompdf->output();
            exit;
        } finally {
            if ($oldDisplayErrors !== false) {
                ini_set('display_errors', (string) $oldDisplayErrors);
            }
        }
    }

    private function hidratarQuestoesComAlternativas(\Exam $exam, array $questoes): array
    {
        foreach ($questoes as $idx => $q) {
            $questoes[$idx]['alternativas'] = $exam->getAlternativas((int) ($q['id'] ?? 0));
        }
        return $questoes;
    }

    private function resolveLogoDataUri(): string
    {
        try {
            $url = (string) \LayoutHelper::getNavbarLogoUrl();
            if ($url === '') {
                return '';
            }
            if (str_starts_with($url, 'data:')) {
                return $url;
            }
            $parts = parse_url($url) ?: [];
            $caminhoArquivo = '';
            if (!empty($parts['path'])) {
                $relative = ltrim((string) $parts['path'], '/');
                foreach ([__DIR__ . '/../../../public/' . $relative, __DIR__ . '/../../../' . $relative] as $candidate) {
                    if (is_file($candidate) && is_readable($candidate)) {
                        $caminhoArquivo = $candidate;
                        break;
                    }
                }
            }
            if ($caminhoArquivo === '') {
                return preg_match('#^https?://#i', $url) ? $url : '';
            }
            $bin = @file_get_contents($caminhoArquivo);
            if (!is_string($bin) || $bin === '') {
                return '';
            }
            $ext = strtolower((string) pathinfo($caminhoArquivo, PATHINFO_EXTENSION));
            $mimeMap = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
            $mime = $mimeMap[$ext] ?? 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode($bin);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function renderProvaAdaptadaPdfHtml(array $prova, array $questoes, array $aluno, string $logoData): string
    {
        $h = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $pdfContent = static function (string $html): string {
            $html = \LayoutHelper::renderEnunciadoProva($html);
            $html = preg_replace('#</?(table|thead|tbody|tfoot|tr|td|th)(?:\s[^>]*)?>#i', ' ', $html) ?? $html;
            $html = preg_replace('/\s(?:width|height)=["\'][^"\']*["\']/i', '', $html) ?? $html;
            $html = preg_replace('/\sstyle=["\'][^"\']*["\']/i', '', $html) ?? $html;
            $html = preg_replace('/<img\b([^>]*)>/i', '<img$1 style="max-width:660px;width:auto;height:auto;max-height:430px;">', $html) ?? $html;
            return preg_replace('/\s+/', ' ', $html) ?? $html;
        };
        $pdfInlineContent = static function (string $html) use ($pdfContent): string {
            $html = $pdfContent($html);
            $html = preg_replace('#</?(p|div|section|article|h[1-6])(?:\s[^>]*)?>#i', ' ', $html) ?? $html;
            $html = preg_replace('#<br\s*/?>#i', ' ', $html) ?? $html;
            return trim(preg_replace('/\s+/', ' ', $html) ?? $html);
        };
        $baseUrl = defined('URL') ? rtrim((string) URL, '/') : '';
        $imageUrl = static function (?string $url) use ($baseUrl): string {
            $url = trim((string) $url);
            if ($url === '') {
                return '';
            }
            if (preg_match('#^(https?://|data:)#i', $url)) {
                return $url;
            }
            if (str_starts_with($url, '/')) {
                return $baseUrl !== '' ? $baseUrl . $url : $url;
            }
            return $baseUrl . '/' . ltrim($url, '/');
        };

        $questoesHtml = '';
        foreach ($questoes as $idx => $q) {
            $altsHtml = '';
            if (($q['tipo'] ?? '') === 'multipla_escolha' && !empty($q['alternativas'])) {
                foreach ($q['alternativas'] as $altIdx => $alt) {
                    $letter = chr(65 + (int) $altIdx);
                    $altsHtml .= '<div class="alternativa">'
                        . '<span class="alt-marker"><span class="bolinha"></span><strong>' . $letter . ')</strong></span>'
                        . '<span class="alt-text">' . $pdfInlineContent((string) ($alt['texto'] ?? '')) . '</span>'
                        . '</div>';
                }
            }
            $img = '';
            if (!empty($q['imagem_url'])) {
                $img = '<div class="imagem"><img src="' . $h($imageUrl((string) $q['imagem_url'])) . '" alt="Imagem da questão" style="max-width:660px;width:auto;height:auto;max-height:430px;"></div>';
            }
            $questoesHtml .= '<div class="questao">'
                . '<div class="qhead">Questão ' . ((int) $idx + 1) . ' <span>' . number_format((float) ($q['valor'] ?? 0), 2, ',', '.') . ' pts</span></div>'
                . '<div class="enunciado">' . $pdfContent((string) ($q['enunciado'] ?? '')) . '</div>'
                . $img
                . ($altsHtml !== '' ? '<div class="alternativas">' . $altsHtml . '</div>' : '<div class="resposta-linhas"></div>')
                . '</div>';
        }

        $titulo = (string) ($prova['titulo'] ?? 'Prova adaptada');
        $data = !empty($prova['data_inicio']) ? date('d/m/Y', strtotime((string) $prova['data_inicio'])) : '—';
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><style>
            @page { margin: 16mm 14mm; }
            body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; font-size: 12px; }
            .header { border-bottom: 2px solid #d1d5db; padding-bottom: 12px; margin-bottom: 14px; }
            .logo { margin-bottom: 8px; }
            .logo img { max-width: 110px; max-height: 60px; }
            h1 { font-size: 20px; margin: 0 0 4px; }
            .subtitle { color: #4b5563; font-size: 12px; }
            .meta { margin-top: 12px; border: 1px solid #d1d5db; padding: 8px; }
            .meta-item { margin-bottom: 6px; }
            .label { color: #6b7280; font-weight: 700; text-transform: uppercase; font-size: 9px; letter-spacing: .04em; }
            .linha-assinatura { margin-top: 10px; }
            .campo-assinatura { margin-bottom: 8px; color: #4b5563; }
            .assinatura { border-bottom: 1px solid #9ca3af; height: 22px; }
            .questao { border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 14px; }
            .qhead { font-weight: 700; font-size: 13px; margin-bottom: 8px; }
            .qhead span { font-weight: 400; color: #6b7280; margin-left: 8px; }
            .enunciado { line-height: 1.55; margin-bottom: 8px; }
            .enunciado img, .imagem img { max-width: 660px; width: auto; height: auto; max-height: 430px; border: 1px solid #e5e7eb; }
            .alternativas { margin-top: 8px; }
            .alternativa { margin: 8px 0; line-height: 1.45; page-break-inside: avoid; }
            .alt-marker { display: inline-block; width: 48px; vertical-align: top; }
            .alt-text { display: inline-block; width: 600px; vertical-align: top; }
            .alt-text img { max-width: 600px; width: auto; height: auto; max-height: 360px; }
            .bolinha { display: inline-block; width: 10px; height: 10px; border: 1px solid #111827; border-radius: 50%; margin-right: 6px; }
            .resposta-linhas { height: 90px; border-bottom: 1px solid #d1d5db; }
            .footer { margin-top: 20px; color: #9ca3af; font-size: 9px; text-align: right; }
        </style></head><body>';

        $html .= '<div class="header">'
            . '<div class="logo">' . ($logoData !== '' ? '<img src="' . $h($logoData) . '" alt="Logo">' : '') . '</div>'
            . '<div class="title"><h1>' . $h($titulo) . '</h1><div class="subtitle">Prova adaptada - EducaInclui</div></div>'
            . '<div class="meta">'
            . '<div class="meta-item"><span class="label">Aluno:</span> ' . $h($aluno['nome'] ?? '') . ' &nbsp;&nbsp; <span class="label">Turma:</span> ' . $h($aluno['turma_nome'] ?? '—') . '</div>'
            . '<div class="meta-item"><span class="label">Professor:</span> ' . $h($prova['professor_nome'] ?? '—') . ' &nbsp;&nbsp; <span class="label">Matéria:</span> ' . $h($prova['materia_nome'] ?? '—') . '</div>'
            . '<div class="meta-item"><span class="label">Data:</span> ' . $h($data) . ' &nbsp;&nbsp; <span class="label">Valor total:</span> ' . number_format((float) ($prova['valor_total'] ?? 0), 2, ',', '.') . '</div>'
            . '<div class="linha-assinatura"><div class="campo-assinatura">Assinatura do aluno<div class="assinatura"></div></div><div class="campo-assinatura">Nota<div class="assinatura"></div></div></div>'
            . '</div></div>';
        $html .= $questoesHtml;
        $html .= '<div class="footer">Gerado em ' . date('d/m/Y H:i') . '</div></body></html>';
        return $html;
    }

    /**
     * Logs antigos guardavam apenas 500 caracteres. Recompõe o texto completo
     * a partir das questões reais para que o modal mostre o enunciado inteiro.
     */
    private function hidratarMapaReescrita(array $mapa, array $qOriginais, array $qAdaptadas): array
    {
        $norm = static function ($s): string {
            return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s)));
        };

        $adaptadasPorId = [];
        foreach ($qAdaptadas as $qa) {
            $adaptadasPorId[(int) ($qa['id'] ?? 0)] = $qa;
        }

        foreach ($mapa as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }

            $cloneId = (int) ($item['clone_questao_id'] ?? 0);
            if ($cloneId > 0 && isset($adaptadasPorId[$cloneId])) {
                $mapa[$idx]['depois'] = $adaptadasPorId[$cloneId]['enunciado'] ?? ($item['depois'] ?? '');
                $mapa[$idx]['adaptada_questao'] = $adaptadasPorId[$cloneId];
            }

            $antesLog = $norm($item['antes'] ?? '');
            if ($antesLog === '') {
                continue;
            }

            foreach ($qOriginais as $qo) {
                $originalCompleto = $norm($qo['enunciado'] ?? '');
                if ($originalCompleto !== '' && str_starts_with($originalCompleto, $antesLog)) {
                    $mapa[$idx]['antes'] = $qo['enunciado'] ?? ($item['antes'] ?? '');
                    $mapa[$idx]['original_questao'] = $qo;
                    break;
                }
            }
        }

        return $mapa;
    }

    private function mesclarReescritasIndividuais(int $versionId, array $mapa): array
    {
        $porCloneId = [];
        foreach ($mapa as $idx => $item) {
            if (is_array($item) && !empty($item['clone_questao_id'])) {
                $porCloneId[(int) $item['clone_questao_id']] = $idx;
            }
        }

        foreach ($this->auditLog->listDetailsByVersionAction($versionId, 'versao_reescrita_ia_questao', 100) as $item) {
            $cloneId = (int) ($item['clone_questao_id'] ?? 0);
            if ($cloneId <= 0) {
                continue;
            }
            if (isset($porCloneId[$cloneId])) {
                $mapa[$porCloneId[$cloneId]] = array_merge($mapa[$porCloneId[$cloneId]], $item);
                continue;
            }
            $mapa[] = $item;
            $porCloneId[$cloneId] = count($mapa) - 1;
        }

        return $mapa;
    }

    public function refazerQuestaoVersao(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'alterar', false)) {
            return;
        }

        require_once __DIR__ . '/../../Services/AIJobService.php';
        require_once __DIR__ . '/../../Models/Exams/Exam.php';

        $user = $this->auth->getUser();
        $versionId = (int) ($_POST['version_id'] ?? 0);
        $cloneQuestaoId = (int) ($_POST['clone_questao_id'] ?? 0);
        $ver = $this->versions->getById($versionId);
        if (!$ver) {
            $this->setFlashMessage('Versão não encontrada.', 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }

        $rules = $this->rulesFromVersion($ver);
        try {
            \App\Services\AIJobService::rewriteAssessmentVersionQuestionNow([
                'version_id' => $versionId,
                'adapted_prova_id' => (int) ($ver['adapted_prova_id'] ?? 0),
                'clone_questao_id' => $cloneQuestaoId,
                'mascara_id' => (int) ($ver['mascara_id'] ?? 0),
                'aluno_id' => (int) ($ver['aluno_id'] ?? 0),
                'prova_id' => (int) ($ver['prova_id'] ?? 0),
                'user_id' => (int) ($user['id'] ?? 0),
                'original_enunciado' => (string) ($_POST['original_enunciado'] ?? ''),
                'reduce_options_keep' => \MascaraResolver::reduceOptionsKeep($rules),
                'modes' => [
                    'simplified_instructions' => !empty($rules['simplified_instructions']),
                    'shorten_statement' => !empty($rules['shorten_statement']),
                    'literal_language' => !empty($rules['literal_language']),
                ],
            ]);
            $this->setFlashMessage('Questão reescrita novamente por IA.', 'success');
        } catch (\Throwable $e) {
            error_log('EducaIncluiController::refazerQuestaoVersao: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível refazer a questão por IA: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/admin/inclusao/versoes/' . $versionId . '/diff');
    }

    public function editarQuestaoVersao(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'alterar', false)) {
            return;
        }

        require_once __DIR__ . '/../../Models/Exams/Exam.php';

        $user = $this->auth->getUser();
        $versionId = (int) ($_POST['version_id'] ?? 0);
        $cloneQuestaoId = (int) ($_POST['clone_questao_id'] ?? 0);
        $novoEnunciado = trim((string) ($_POST['enunciado'] ?? ''));
        $ver = $this->versions->getById($versionId);
        if (!$ver || $cloneQuestaoId <= 0 || $novoEnunciado === '') {
            $this->setFlashMessage('Dados inválidos para editar a questão.', 'error');
            $this->redirect($versionId > 0 ? ('/admin/inclusao/versoes/' . $versionId . '/diff') : '/admin/inclusao/versoes');
            return;
        }

        $exam = new \Exam();
        $questao = $exam->getQuestaoById($cloneQuestaoId);
        if (!$questao || (int) ($questao['prova_id'] ?? 0) !== (int) ($ver['adapted_prova_id'] ?? 0)) {
            $this->setFlashMessage('Questão não pertence a esta versão adaptada.', 'error');
            $this->redirect('/admin/inclusao/versoes/' . $versionId . '/diff');
            return;
        }

        try {
            $antes = (string) ($questao['enunciado'] ?? '');
            $exam->updateQuestao($cloneQuestaoId, [
                'enunciado' => $novoEnunciado,
                'imagem_url' => $questao['imagem_url'] ?? null,
                'tipo' => $questao['tipo'] ?? 'multipla_escolha',
                'valor' => $questao['valor'] ?? 1.00,
                'nivel_dificuldade' => $questao['nivel_dificuldade'] ?? null,
                'ordem' => $questao['ordem'] ?? 0,
            ]);
            $this->auditLog->record('versao_questao_editada', [
                'versao_adaptada_id' => $versionId,
                'mascara_id' => (int) ($ver['mascara_id'] ?? 0),
                'aluno_id' => (int) ($ver['aluno_id'] ?? 0),
                'prova_id' => (int) ($ver['prova_id'] ?? 0),
                'user_id' => (int) ($user['id'] ?? 0),
                'details' => [
                    'clone_questao_id' => $cloneQuestaoId,
                    'antes' => $antes,
                    'depois' => $novoEnunciado,
                ],
            ]);
            $this->setFlashMessage('Questão editada com sucesso.', 'success');
        } catch (\Throwable $e) {
            error_log('EducaIncluiController::editarQuestaoVersao: ' . $e->getMessage());
            $this->setFlashMessage('Não foi possível editar a questão: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/admin/inclusao/versoes/' . $versionId . '/diff');
    }

    private function rulesFromVersion(array $ver): array
    {
        $rules = [];
        if (!empty($ver['regras_snapshot_json'])) {
            $decoded = json_decode((string) $ver['regras_snapshot_json'], true);
            if (is_array($decoded)) {
                $rules = $decoded;
            }
        }

        $mascaraId = (int) ($ver['mascara_id'] ?? 0);
        if ($mascaraId > 0) {
            try {
                $currentRules = (new \RegraMascara())->getMapaPorMascara($mascaraId);
                if (is_array($currentRules)) {
                    $rules = array_merge($rules, $currentRules);
                }
            } catch (\Throwable $e) {
                error_log('EducaIncluiController::rulesFromVersion: ' . $e->getMessage());
            }
        }

        return $rules;
    }

    public function aprovarVersao(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'alterar', false)) {
            return;
        }
        $user = $this->auth->getUser();
        $versionId = (int) ($_POST['version_id'] ?? 0);
        $acao = (string) ($_POST['acao'] ?? 'aprovar');
        $ver = $this->versions->getById($versionId);
        if (!$ver) {
            $this->setFlashMessage('Versão não encontrada.', 'error');
            $this->redirect('/admin/inclusao/versoes');
            return;
        }

        if ($acao === 'reprovar') {
            $this->versions->reject($versionId, (int) ($user['id'] ?? 0));
            $this->auditLog->record('versao_rejeitada', [
                'versao_adaptada_id' => $versionId,
                'mascara_id' => (int) $ver['mascara_id'],
                'aluno_id' => (int) $ver['aluno_id'],
                'prova_id' => (int) $ver['prova_id'],
                'user_id' => (int) ($user['id'] ?? 0),
            ]);
            $this->setFlashMessage('Versão reprovada.', 'success');
        } else {
            $this->versions->approve($versionId, (int) ($user['id'] ?? 0));
            $this->auditLog->record('versao_aprovada', [
                'versao_adaptada_id' => $versionId,
                'mascara_id' => (int) $ver['mascara_id'],
                'aluno_id' => (int) $ver['aluno_id'],
                'prova_id' => (int) $ver['prova_id'],
                'user_id' => (int) ($user['id'] ?? 0),
            ]);
            $this->setFlashMessage('Versão aprovada. Será entregue ao aluno na próxima abertura da prova.', 'success');
        }
        $this->redirect('/admin/inclusao/versoes');
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('inclusao', 'visualizar', false)) {
            return;
        }

        $search = trim((string) ($_GET['busca'] ?? ''));
        $mascaras = $this->accommodations->listForAdmin($search);

        $alunosEncontrados = [];
        if ($search !== '') {
            $alunosEncontrados = $this->db->fetchAll(
                "SELECT a.id, a.nome, t.nome AS turma_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.ativo = 1 AND a.nome LIKE :busca
                 ORDER BY a.nome
                 LIMIT 30",
                ['busca' => '%' . $search . '%']
            );
        }

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/inclusao/index', [
            'title' => 'EducaInclui - Acessibilidade',
            'page_title' => 'EducaInclui',
            'current_page' => 'inclusao',
            'user' => $this->auth->getUser(),
            'busca' => $search,
            'mascaras' => $mascaras,
            'alunos_encontrados' => $alunosEncontrados,
            'flash' => $flash,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Resumo JSON da máscara do aluno, para o offcanvas de acesso rápido no perfil do aluno.
     */
    public function resumoJson(int $alunoId): void
    {
        if (!$this->enforceAdminPermissionKey('inclusao', 'visualizar')) {
            return;
        }

        $aluno = $this->db->fetch("SELECT id, nome FROM alunos WHERE id = :id", ['id' => $alunoId]);
        if (!$aluno) {
            $this->json(['error' => 'not_found'], 404);
            return;
        }

        $accommodation = $this->accommodations->getByAluno($alunoId);
        $accId = $accommodation ? (int) $accommodation['id'] : 0;
        $rulesMap = $accId ? $this->rules->getMapaPorMascara($accId) : [];
        $docs = $accId ? $this->documents->listarPorMascara($accId) : [];

        $catalog = CatalogoRegraMascara::rules();
        $regrasAtivas = [];
        foreach ($rulesMap as $key => $value) {
            if (!isset($catalog[$key]) || trim((string) $value) === '') {
                continue;
            }
            $def = $catalog[$key];
            $label = (string) $def['label'];
            if (($def['input'] ?? '') !== 'toggle') {
                $label .= ': ' . $value;
            }
            $regrasAtivas[] = $label;
        }

        $this->json([
            'aluno_nome' => (string) $aluno['nome'],
            'has_accommodation' => $accId > 0,
            'status' => (string) ($accommodation['status'] ?? ''),
            'tipo_adaptacao' => (string) ($accommodation['tipo_adaptacao'] ?? ''),
            'regras_ativas' => $regrasAtivas,
            'laudo_count' => count($docs),
            'manage_url' => URL . '/admin/inclusao/aluno/' . $alunoId,
        ]);
    }

    public function manage(int $alunoId): void
    {
        if (!$this->enforceAdminPermissionKey('inclusao', 'visualizar', false)) {
            return;
        }
        $aluno = $this->db->fetch(
            "SELECT a.id, a.nome, t.nome AS turma_nome
             FROM alunos a LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = :id",
            ['id' => $alunoId]
        );
        if (!$aluno) {
            $this->setFlashMessage('Aluno não encontrado.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }

        $accommodation = $this->accommodations->getByAluno($alunoId);
        $accId = $accommodation ? (int) $accommodation['id'] : 0;
        $rulesMap = $accId ? $this->rules->getMapaPorMascara($accId) : [];
        $docs = $accId ? $this->documents->listarPorMascara($accId) : [];
        $logs = $accId ? $this->auditLog->listarPorMascara($accId, 20) : [];

        $isSignificativa = $accommodation && ($accommodation['tipo_adaptacao'] ?? '') === 'significativa';
        $reinforcedApprovers = [];
        $reinforcedCount = 0;
        $userAlreadyApproved = false;
        $user = $this->auth->getUser();
        if ($accId && $isSignificativa) {
            $reinforcedApprovers = $this->auditLog->listApprovers($accId, EducaIncluiService::ACTION_REINFORCED_APPROVAL);
            $reinforcedCount = count($reinforcedApprovers);
            $userAlreadyApproved = $this->auditLog->userHasApproved(
                $accId,
                EducaIncluiService::ACTION_REINFORCED_APPROVAL,
                (int) ($user['id'] ?? 0)
            );
        }

        $aiSuggestion = $accId ? $this->auditLog->latestDetailsByAction($accId, 'laudo_analisado') : null;

        $initialStep = (int) ($_GET['step'] ?? 1);
        if ($initialStep < 1 || $initialStep > 5) {
            $initialStep = 1;
        }
        // Sem máscara salva ainda, só a Etapa 1 faz sentido (Etapa 2 exige accId para o upload).
        if ($accId === 0 && $initialStep > 1) {
            $initialStep = 1;
        }
        $activeAiJobId = (int) ($_GET['ai_job'] ?? 0);

        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/inclusao/manage', [
            'title' => 'Máscara de Acessibilidade',
            'page_title' => 'EducaInclui',
            'current_page' => 'inclusao',
            'user' => $user,
            'aluno' => $aluno,
            'accommodation' => $accommodation,
            'rules_map' => $rulesMap,
            'catalog' => CatalogoRegraMascara::rules(),
            'documentos' => $docs,
            'logs' => $logs,
            'ai_suggestion' => $aiSuggestion,
            'doc_encryption_on' => $this->service->isDocEncryptionAvailable(),
            'is_significativa' => $isSignificativa,
            'reinforced_approvers' => $reinforcedApprovers,
            'reinforced_count' => $reinforcedCount,
            'reinforced_required' => EducaIncluiService::REINFORCED_APPROVALS_REQUIRED,
            'user_already_approved' => $userAlreadyApproved,
            'initial_step' => $initialStep,
            'active_ai_job_id' => $activeAiJobId,
            'flash' => $flash,
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function salvar(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'alterar', false)) {
            return;
        }

        $user = $this->auth->getUser();
        $userId = (int) ($user['id'] ?? 0);
        $result = $this->service->saveMask($_POST, $userId);
        $alunoId = (int) ($_POST['aluno_id'] ?? 0);
        $nextStep = (int) ($_POST['_next_step'] ?? 0);

        if (!empty($result['errors'])) {
            $this->setFlashMessage(implode(' ', $result['errors']), 'error');
            // Sem mascara_id novo criado: nunca avança pra uma etapa que dependa dele.
            $this->redirect('/admin/inclusao/aluno/' . $alunoId);
            return;
        }

        $andActivate = ($_POST['and_activate'] ?? '') === '1';
        if ($andActivate) {
            $activateResult = $this->service->activate((int) $result['id'], $userId);
            if ($activateResult['ok']) {
                $this->setFlashMessage('Máscara salva e ativada com sucesso.', 'success');
            } else {
                $this->setFlashMessage('Máscara salva, mas não foi possível ativar: ' . $activateResult['error'], 'error');
            }
        } else {
            $this->setFlashMessage('Máscara salva com sucesso.', 'success');
        }

        $redirectStep = $nextStep > 0 ? $nextStep : ($andActivate ? 5 : 0);
        $this->redirect('/admin/inclusao/aluno/' . $alunoId . ($redirectStep > 0 ? '?step=' . $redirectStep : ''));
    }

    public function ativar(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'alterar', false)) {
            return;
        }

        $user = $this->auth->getUser();
        $mascaraId = (int) ($_POST['mascara_id'] ?? 0);
        $result = $this->service->activate($mascaraId, (int) ($user['id'] ?? 0));
        $acc = $this->accommodations->getById($mascaraId);

        $this->setFlashMessage($result['ok'] ? 'Máscara ativada.' : $result['error'], $result['ok'] ? 'success' : 'error');
        $this->redirect('/admin/inclusao/aluno/' . (int) ($acc['aluno_id'] ?? 0) . '?step=5');
    }

    /**
     * EducaInclui — dispara a análise do laudo por IA (OCR + sugestão de máscara), assíncrona.
     */
    public function analisarLaudo(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'alterar', false)) {
            return;
        }
        $user = $this->auth->getUser();
        $mascaraId = (int) ($_POST['mascara_id'] ?? 0);
        $acc = $this->accommodations->getById($mascaraId);
        if (!$acc) {
            $this->setFlashMessage('Máscara não encontrada.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }

        require_once __DIR__ . '/../../Services/AIJobService.php';
        require_once __DIR__ . '/../../Services/CreditosService.php';
        require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
        $jobId = 0;
        $creditsRef = 'mascara:' . $mascaraId;
        $creditsModulo = 'educainclui_analisar_laudo';
        $debitou = false;
        try {
            $creditos = new \App\Services\CreditosService();
            if (!$creditos->podeConsumir('escola', \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, $creditsModulo)) {
                throw new \RuntimeException('TudiCoins insuficientes na carteira da escola para analisar o laudo.');
            }
            $creditos->consumirEscola($creditsModulo, $creditsRef);
            $debitou = true;
            $jobId = \App\Services\AIJobService::enqueue('inclusao_analisar_laudo', [
                'mascara_id' => $mascaraId,
                'aluno_id' => (int) $acc['aluno_id'],
                'user_id' => (int) ($user['id'] ?? 0),
                'credits_ref' => $creditsRef,
                'credits_modulo' => $creditsModulo,
            ], (int) ($user['id'] ?? 0), 'admin');
        } catch (\Throwable $e) {
            if ($debitou) {
                try {
                    (new \App\Services\CreditosService())->estornarPorReferencia($creditsModulo, $creditsRef);
                } catch (\Throwable $eEstorno) {
                    error_log('EducaInclui estorno laudo: ' . $eEstorno->getMessage());
                }
            }
            $this->setFlashMessage('Não foi possível iniciar a análise: ' . $e->getMessage(), 'error');
        }
        $step = '?step=2' . ($jobId > 0 ? '&ai_job=' . $jobId : '');
        $this->redirect('/admin/inclusao/aluno/' . (int) $acc['aluno_id'] . $step);
    }

    public function aprovarReforcada(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'alterar', false)) {
            return;
        }

        $user = $this->auth->getUser();
        $mascaraId = (int) ($_POST['mascara_id'] ?? 0);
        $result = $this->service->registerReinforcedApproval($mascaraId, (int) ($user['id'] ?? 0));
        $acc = $this->accommodations->getById($mascaraId);

        if ($result['ok']) {
            $this->setFlashMessage('Aprovação registrada (' . $result['count'] . '/' . EducaIncluiService::REINFORCED_APPROVALS_REQUIRED . ').', 'success');
        } else {
            $this->setFlashMessage($result['error'], 'error');
        }
        $this->redirect('/admin/inclusao/aluno/' . (int) ($acc['aluno_id'] ?? 0) . '?step=5');
    }

    public function status(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'alterar', false)) {
            return;
        }

        $user = $this->auth->getUser();
        $mascaraId = (int) ($_POST['mascara_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $acc = $this->accommodations->getById($mascaraId);
        $this->service->setStatus($mascaraId, $status, (int) ($user['id'] ?? 0));
        $this->setFlashMessage('Status atualizado.', 'success');
        $this->redirect('/admin/inclusao/aluno/' . (int) ($acc['aluno_id'] ?? 0) . '?step=5');
    }

    public function uploadLaudo(): void
    {
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }
        if (!$this->enforceAdminPermissionKey('inclusao', 'cadastrar', false)) {
            return;
        }

        $user = $this->auth->getUser();
        $mascaraId = (int) ($_POST['mascara_id'] ?? 0);
        $acc = $this->accommodations->getById($mascaraId);
        if (!$acc) {
            $this->setFlashMessage('Máscara não encontrada.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }

        $file = $_FILES['laudo'] ?? null;
        if (!is_array($file)) {
            $this->setFlashMessage('Nenhum arquivo enviado.', 'error');
            $this->redirect('/admin/inclusao/aluno/' . (int) $acc['aluno_id'] . '?step=2');
            return;
        }
        $result = $this->service->uploadLaudo($mascaraId, $file, (int) ($user['id'] ?? 0));
        $this->setFlashMessage($result['ok'] ? 'Laudo enviado e criptografado.' : $result['error'], $result['ok'] ? 'success' : 'error');
        $this->redirect('/admin/inclusao/aluno/' . (int) $acc['aluno_id'] . '?step=2');
    }

    public function laudo(int $docId): void
    {
        if (!$this->enforceAdminPermissionKey('inclusao', 'visualizar', false)) {
            return;
        }
        $doc = $this->documents->getById($docId);
        if (!$doc) {
            $this->setFlashMessage('Documento não encontrado.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }

        $content = $this->service->decryptDocument($doc);
        if ($content === null) {
            $this->setFlashMessage('Não foi possível abrir o laudo.', 'error');
            $this->redirect('/admin/inclusao');
            return;
        }

        $user = $this->auth->getUser();
        $this->auditLog->record('laudo_visualizado', [
            'mascara_id' => (int) $doc['mascara_id'],
            'user_id' => (int) ($user['id'] ?? 0),
            'details' => ['document_id' => $docId],
        ]);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . ($doc['tipo_mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="laudo-' . $docId . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        echo $content;
        exit;
    }
}
}
