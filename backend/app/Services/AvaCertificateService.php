<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/LayoutHelper.php';
require_once __DIR__ . '/../Models/Ava/Certificate.php';
require_once __DIR__ . '/../Models/Ava/Discipline.php';
require_once __DIR__ . '/../Models/Ava/DisciplineEnrollment.php';

/**
 * EducaTudo - AvaCertificateService
 *
 * Regras de emissão e geração do PDF de certificado de conclusão (por disciplina).
 * Conclusão = ava_matriculas_disciplina.status='concluida' + ava_cursos.certificacao=1.
 */
class AvaCertificateService
{
    private $db;
    private Certificate $model;
    private Discipline $disciplines;
    private DisciplineEnrollment $enrollments;
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->db = Database::getInstance();
        $this->model = new Certificate();
        $this->disciplines = new Discipline();
        $this->enrollments = new DisciplineEnrollment();
        $this->config = $config;
    }

    public function model(): Certificate
    {
        return $this->model;
    }

    /**
     * Verifica se o aluno pode emitir certificado da disciplina.
     * @return array{ok:bool,reason:string,context:array<string,mixed>}
     */
    public function canIssueDiscipline(int $alunoId, int $disciplinaId): array
    {
        $fail = static fn(string $r): array => ['ok' => false, 'reason' => $r, 'context' => []];

        $matricula = $this->enrollments->find($alunoId, $disciplinaId);
        if (!$matricula) {
            return $fail('Você não está matriculado nesta disciplina.');
        }
        if (($matricula['status'] ?? '') !== 'concluida') {
            return $fail('O certificado fica disponível após a conclusão da disciplina.');
        }

        $disciplina = $this->disciplines->find($disciplinaId);
        if (!$disciplina) {
            return $fail('Disciplina não encontrada.');
        }

        $curso = $this->db->fetch(
            "SELECT id, nome, certificacao, carga_horaria FROM ava_cursos WHERE id = :id",
            ['id' => (int) ($disciplina['curso_id'] ?? 0)]
        );
        if (!$curso || (int) ($curso['certificacao'] ?? 0) !== 1) {
            return $fail('A emissão de certificado não está habilitada para este curso.');
        }

        $carga = (int) ($disciplina['carga_horaria'] ?? 0);
        if ($carga <= 0) {
            $carga = (int) ($curso['carga_horaria'] ?? 0);
        }

        return [
            'ok' => true,
            'reason' => '',
            'context' => [
                'matricula' => $matricula,
                'disciplina' => $disciplina,
                'curso' => $curso,
                'aluno_nome' => $this->studentName($alunoId),
                'carga_horaria' => $carga,
                'nota_final' => $matricula['nota_final'] ?? null,
            ],
        ];
    }

    /**
     * Garante o registro do certificado (idempotente) e retorna a linha.
     * @param array<string,mixed> $context  retornado por canIssueDiscipline()
     * @return array<string,mixed>
     */
    public function issueDiscipline(int $alunoId, int $disciplinaId, array $context): array
    {
        $existing = $this->model->findForStudentDiscipline($alunoId, $disciplinaId);
        if ($existing) {
            return $existing;
        }
        $disciplina = $context['disciplina'] ?? [];
        $id = $this->model->create([
            'aluno_id' => $alunoId,
            'disciplina_id' => $disciplinaId,
            'curso_id' => (int) ($disciplina['curso_id'] ?? 0) ?: null,
            'tipo' => 'disciplina',
            'codigo' => $this->generateCode(),
            'aluno_nome' => (string) ($context['aluno_nome'] ?? ''),
            'titulo' => (string) ($disciplina['nome'] ?? ''),
            'carga_horaria' => (int) ($context['carga_horaria'] ?? 0),
            'nota_final' => $context['nota_final'] ?? null,
        ]);
        return $this->model->find($id) ?? [];
    }

    /** Gera um código único de validação. */
    public function generateCode(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $code = 'AVA-' . strtoupper(bin2hex(random_bytes(5)));
            if (!$this->model->codeExists($code)) {
                return $code;
            }
        }
        return 'AVA-' . strtoupper(bin2hex(random_bytes(8)));
    }

    /** URL pública de validação do certificado. */
    public function validationUrl(string $codigo): string
    {
        $base = defined('URL') ? rtrim((string) URL, '/') : '';
        return $base . '/certificado/validar/' . rawurlencode($codigo);
    }

    /**
     * HTML do certificado (A4 paisagem) para o dompdf.
     * @param array<string,mixed> $cert
     */
    public function renderHtml(array $cert): string
    {
        $escola = (string) LayoutHelper::getSystemTitle();
        $logo = $this->resolveSchoolLogoForPdf();
        $validationUrl = $this->validationUrl((string) ($cert['codigo'] ?? ''));

        $viewData = [
            'cert' => $cert,
            'escola' => $escola !== '' ? $escola : 'EducaTudo',
            'logo_data' => $logo,
            'validation_url' => $validationUrl,
            'emitido_em' => !empty($cert['emitido_em']) ? date('d/m/Y', strtotime((string) $cert['emitido_em'])) : date('d/m/Y'),
        ];
        $templateFile = __DIR__ . '/../Views/ava/certificado_pdf.php';
        ob_start();
        extract($viewData, EXTR_SKIP);
        require $templateFile;
        return (string) ob_get_clean();
    }

    private function studentName(int $alunoId): string
    {
        $row = $this->db->fetch("SELECT nome FROM alunos WHERE id = :id", ['id' => $alunoId]);
        return trim((string) ($row['nome'] ?? ''));
    }

    /** Logo da escola como data URI (reaproveita o padrão das Declarações). */
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
                require_once __DIR__ . '/MediaStorageService.php';
                $media = new MediaStorageService($this->config);
                $localPath = $media->getLocalPath($type, $key);
                if ($localPath !== null && is_file($localPath) && is_readable($localPath)) {
                    $filePath = $localPath;
                }
            }
            if ($filePath === '' && !empty($parts['path'])) {
                $relative = ltrim((string) $parts['path'], '/');
                foreach ([__DIR__ . '/../../public/' . $relative, __DIR__ . '/../../' . $relative] as $cand) {
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
        } catch (Throwable $e) {
            return '';
        }
    }
}
