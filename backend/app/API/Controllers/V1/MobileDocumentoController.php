<?php

namespace App\API\Controllers\V1;

require_once __DIR__ . '/../../../Policies/ParentStudentAccessPolicy.php';

use App\Core\BaseController;
use App\Core\Database;
use App\Middleware\MobileApiAuthMiddleware;
use App\Modulos\DocumentosFamilia\Services\DocumentoFamiliaService;

class MobileDocumentoController extends BaseController
{
    private $db;
    private \ParentStudentAccessPolicy $policy;
    private DocumentoFamiliaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->policy = new \ParentStudentAccessPolicy();
        $this->service = new DocumentoFamiliaService($this->db);
    }

    public function index($studentId): void
    {
        $aluno = $this->aluno($studentId);
        if (!$this->service->pronto()) {
            $this->json(['data' => ['files' => [], 'health' => null, 'declarations' => []], 'meta' => ['available' => false]]);
            return;
        }
        $this->json([
            'data' => [
                'files' => array_map(fn ($row) => $this->arquivo($row), $this->service->arquivos((int) $aluno['id'])),
                'health' => $this->ficha($this->service->ficha((int) $aluno['id'])),
                'declarations' => array_map(fn ($row) => $this->declaracao($row), $this->service->declaracoesDoAluno((int) $aluno['id'], $this->parentId())),
            ],
            'meta' => ['available' => true],
        ]);
    }

    public function download($studentId, $fileId): void
    {
        $aluno = $this->aluno($studentId);
        $id = filter_var($fileId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->fail('file_not_found', 'Arquivo não encontrado.', 404);
        }
        $arquivo = $this->service->caminhoArquivo((int) $id, (int) $aluno['id']);
        if ($arquivo === null) {
            $this->fail('file_not_found', 'Arquivo não encontrado.', 404);
        }
        $this->enviarArquivo($arquivo);
    }

    public function upload($studentId): void
    {
        $aluno = $this->aluno($studentId);
        if (!$this->service->pronto()) {
            $this->fail('unavailable', 'Os documentos da família ainda não estão disponíveis.', 503);
        }
        $titulo = trim((string) ($_POST['titulo'] ?? $_POST['title'] ?? ''));
        $salvo = $this->service->guardarArquivo((int) $aluno['id'], $this->parentId(), $titulo, $_FILES['arquivo'] ?? $_FILES['file'] ?? []);
        if ($salvo === null) {
            $this->fail('invalid_file', 'Envie um PDF ou uma imagem de até 10 MB.', 422);
        }
        $this->json(['data' => $this->arquivo($salvo)], 201);
    }

    public function saveHealth($studentId): void
    {
        $aluno = $this->aluno($studentId);
        if (!$this->service->pronto()) {
            $this->fail('unavailable', 'A ficha de saúde ainda não está disponível.', 503);
        }
        $input = $this->input();
        $ficha = $this->service->salvarFicha((int) $aluno['id'], $this->parentId(), [
            'tipo_sanguineo' => (string) ($input['blood_type'] ?? $input['tipo_sanguineo'] ?? ''),
            'alergias' => (string) ($input['allergies'] ?? $input['alergias'] ?? ''),
            'medicamentos' => (string) ($input['medications'] ?? $input['medicamentos'] ?? ''),
            'condicoes' => (string) ($input['conditions'] ?? $input['condicoes'] ?? ''),
            'plano_saude' => (string) ($input['health_plan'] ?? $input['plano_saude'] ?? ''),
            'emergencia_nome' => (string) ($input['emergency_name'] ?? $input['emergencia_nome'] ?? ''),
            'emergencia_telefone' => (string) ($input['emergency_phone'] ?? $input['emergencia_telefone'] ?? ''),
            'observacoes' => (string) ($input['notes'] ?? $input['observacoes'] ?? ''),
        ]);
        $this->json(['data' => $this->ficha($ficha)]);
    }

    public function accept($studentId, $declarationId): void
    {
        $aluno = $this->aluno($studentId);
        if (!$this->service->pronto()) {
            $this->fail('unavailable', 'As declarações ainda não estão disponíveis.', 503);
        }
        $id = filter_var($declarationId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->fail('declaration_not_found', 'Declaração não encontrada.', 404);
        }
        $aceite = $this->service->aceitar((int) $id, (int) $aluno['id'], $this->parentId());
        if ($aceite === null) {
            $this->fail('declaration_not_found', 'Declaração não encontrada.', 404);
        }
        $this->json(['data' => $aceite]);
    }

    private function aluno($raw): array
    {
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || !$this->policy->canAccess($this->parentId(), (int) $id)) {
            $this->fail('student_not_found', 'Aluno não encontrado.', 404);
        }
        $row = $this->db->fetch('SELECT id, nome FROM alunos WHERE id = :id', ['id' => (int) $id]);
        if (!$row) {
            $this->fail('student_not_found', 'Aluno não encontrado.', 404);
        }

        return $row;
    }

    private function arquivo(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['titulo'] ?? ''),
            'name' => (string) ($row['arquivo_nome'] ?? ''),
            'url' => '',
            'mime_type' => (string) ($row['tipo_arquivo'] ?? ''),
            'size' => isset($row['tamanho']) ? (int) $row['tamanho'] : null,
            'created_at' => $this->iso($row['created_at'] ?? null),
        ];
    }

    private function ficha(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'blood_type' => (string) ($row['tipo_sanguineo'] ?? ''),
            'allergies' => (string) ($row['alergias'] ?? ''),
            'medications' => (string) ($row['medicamentos'] ?? ''),
            'conditions' => (string) ($row['condicoes'] ?? ''),
            'health_plan' => (string) ($row['plano_saude'] ?? ''),
            'emergency_name' => (string) ($row['emergencia_nome'] ?? ''),
            'emergency_phone' => (string) ($row['emergencia_telefone'] ?? ''),
            'notes' => (string) ($row['observacoes'] ?? ''),
            'hash' => (string) ($row['hash_registro'] ?? ''),
            'updated_at' => $this->iso($row['atualizado_em'] ?? null),
        ];
    }

    private function declaracao(array $row): array
    {
        $hash = trim((string) ($row['texto_hash'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['titulo'] ?? ''),
            'text' => (string) ($row['texto'] ?? ''),
            'version' => (int) ($row['versao'] ?? 1),
            'accepted' => $hash !== '',
            'hash' => $hash !== '' ? $hash : null,
            'accepted_at' => $this->iso($row['aceito_em'] ?? null),
        ];
    }

    private function parentId(): int
    {
        return (int) MobileApiAuthMiddleware::$parentId;
    }

    private function input(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : $_POST;
    }

    private function iso($value): ?string
    {
        if (!$value) {
            return null;
        }
        $time = strtotime((string) $value);

        return $time === false ? null : date(DATE_ATOM, $time);
    }

    private function enviarArquivo(array $arquivo): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        $nome = str_replace(['"', "\r", "\n"], '', (string) $arquivo['nome']);
        header('Content-Type: ' . $arquivo['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . $nome . '"');
        header('Content-Length: ' . (string) filesize($arquivo['path']));
        readfile($arquivo['path']);
        exit;
    }

    private function fail(string $code, string $message, int $status): void
    {
        $this->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}

if (!class_exists('MobileDocumentoController', false) && class_exists(__NAMESPACE__ . '\\MobileDocumentoController', false)) {
    class_alias(__NAMESPACE__ . '\\MobileDocumentoController', 'MobileDocumentoController');
}
