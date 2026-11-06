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
        $alunoId = (int) $aluno['id'];
        $declaracoes = $this->service->pronto()
            ? $this->service->declaracoesDoAluno($alunoId, $this->parentId())
            : [];
        $this->json([
            'data' => [
                'files' => $this->service->checklistDoAluno($alunoId),
                'health' => $this->ficha($this->service->fichaComplementar($alunoId)),
                'declarations' => array_map(fn ($row) => $this->declaracao($row), $declaracoes),
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
        $conteudo = $this->service->conteudoChecklist((int) $aluno['id'], (int) $id, $this->config);
        if ($conteudo === null) {
            $this->fail('file_not_found', 'Arquivo não encontrado.', 404);
        }
        $this->enviarConteudo($conteudo);
    }

    public function upload($studentId): void
    {
        $aluno = $this->aluno($studentId);
        $tipo = trim((string) ($_POST['tipo'] ?? $_POST['type'] ?? ''));
        $salvo = $this->service->anexarChecklist((int) $aluno['id'], $tipo, $_FILES['arquivo'] ?? $_FILES['file'] ?? [], $this->config);
        if ($salvo === null) {
            $this->fail('invalid_file', 'Anexe um PDF ou uma imagem de até 10 MB em um documento pendente.', 422);
        }
        $this->json(['data' => $salvo], 201);
    }

    public function saveHealth($studentId): void
    {
        $aluno = $this->aluno($studentId);
        $input = $this->input();
        $campos = [
            'usa_transporte_escolar' => $input['usa_transporte_escolar'] ?? $input['uses_school_transport'] ?? 0,
        ];
        foreach ([
            'tipo_sanguineo', 'alergias', 'medicamentos_uso', 'condicoes_cronicas', 'deficiencias_obs',
            'plano_saude', 'plano_saude_numero', 'hospital_referencia',
            'contato_emergencia_nome', 'contato_emergencia_telefone', 'contato_emergencia_parentesco',
            'restricoes_alimentares', 'alimentacao_obs',
            'transporte_tipo', 'transporte_rota', 'transporte_ponto', 'transporte_responsavel', 'transporte_telefone',
            'observacoes_gerais',
        ] as $campo) {
            $campos[$campo] = (string) ($input[$campo] ?? '');
        }
        $ficha = $this->service->salvarFichaComplementar((int) $aluno['id'], $campos);
        if ($ficha === []) {
            $this->fail('unavailable', 'A ficha do aluno ainda não está disponível.', 503);
        }
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

    private function ficha(array $row): array
    {
        $saida = ['usa_transporte_escolar' => !empty($row['usa_transporte_escolar'])];
        foreach ([
            'tipo_sanguineo', 'alergias', 'medicamentos_uso', 'condicoes_cronicas', 'deficiencias_obs',
            'plano_saude', 'plano_saude_numero', 'hospital_referencia',
            'contato_emergencia_nome', 'contato_emergencia_telefone', 'contato_emergencia_parentesco',
            'restricoes_alimentares', 'alimentacao_obs',
            'transporte_tipo', 'transporte_rota', 'transporte_ponto', 'transporte_responsavel', 'transporte_telefone',
            'observacoes_gerais',
        ] as $campo) {
            $saida[$campo] = (string) ($row[$campo] ?? '');
        }
        $momento = (string) ($row['updated_at'] ?? '');
        $saida['updated_at'] = $this->iso($momento !== '' ? $momento : null);
        $saida['hash'] = hash('sha256', $momento . '|' . implode('|', $saida));

        return $saida;
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

    private function enviarConteudo(array $arquivo): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        $nome = str_replace(['"', "\r", "\n"], '', (string) $arquivo['nome']);
        header('Content-Type: ' . $arquivo['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . $nome . '"');
        header('Content-Length: ' . (string) strlen((string) $arquivo['bytes']));
        echo $arquivo['bytes'];
        exit;
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
