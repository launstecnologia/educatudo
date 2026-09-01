<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../Models/Oficio.php';
require_once __DIR__ . '/VidaEscolarPdfService.php';

use App\Modulos\VidaEscolar\Models\Oficio;

/**
 * Numeração, persistência e emissão de ofícios da secretaria.
 */
class OficioService
{
    private Oficio $model;

    public function __construct(?Oficio $model = null)
    {
        $this->model = $model ?? new Oficio();
    }

    public function model(): Oficio
    {
        return $this->model;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,id?:int,error?:string}
     */
    public function salvar(array $input, ?int $id, int $usuarioId): array
    {
        if (!$this->model->schemaPronto()) {
            return ['success' => false, 'error' => 'Aplique a migration 2026_08_31_secretaria_oficios no painel Master.'];
        }

        $existente = $id && $id > 0 ? $this->model->findById($id) : null;
        if ($id && $id > 0 && !$existente) {
            return ['success' => false, 'error' => 'Ofício não encontrado.'];
        }
        if (is_array($existente) && (string) ($existente['status'] ?? '') !== 'rascunho') {
            return ['success' => false, 'error' => 'Só é possível editar ofício em rascunho.'];
        }

        $destinatario = trim((string) ($input['destinatario'] ?? ''));
        $assunto = trim((string) ($input['assunto'] ?? ''));
        $corpo = trim((string) ($input['corpo'] ?? ''));
        if ($destinatario === '' || $assunto === '' || $corpo === '') {
            return ['success' => false, 'error' => 'Informe destinatário, assunto e texto do ofício.'];
        }
        if (mb_strlen($destinatario) > 255 || mb_strlen($assunto) > 255) {
            return ['success' => false, 'error' => 'Destinatário e assunto devem ter no máximo 255 caracteres.'];
        }

        $dataOficio = trim((string) ($input['data_oficio'] ?? ''));
        $dt = \DateTime::createFromFormat('Y-m-d', $dataOficio);
        if (!$dt || $dt->format('Y-m-d') !== $dataOficio) {
            return ['success' => false, 'error' => 'Data do ofício inválida.'];
        }

        $alunoId = (int) ($input['aluno_id'] ?? 0);
        $turmaId = (int) ($input['turma_id'] ?? 0);
        if ($alunoId > 0) {
            $aluno = $this->model->alunoPorId($alunoId);
            if (!$aluno) {
                return ['success' => false, 'error' => 'Aluno não encontrado.'];
            }
            if ($turmaId <= 0) {
                $turmaId = (int) ($aluno['turma_id'] ?? 0);
            }
        }
        if ($turmaId > 0 && !$this->model->turmaExiste($turmaId)) {
            return ['success' => false, 'error' => 'Turma não encontrada.'];
        }
        $payload = [
            'data_oficio' => $dataOficio,
            'destinatario' => $destinatario,
            'cargo_destinatario' => $this->limite(trim((string) ($input['cargo_destinatario'] ?? '')), 255),
            'instituicao' => $this->limite(trim((string) ($input['instituicao'] ?? '')), 255),
            'assunto' => $assunto,
            'corpo' => $corpo,
            'aluno_id' => $alunoId > 0 ? $alunoId : null,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
        ];

        if ($existente) {
            $payload['ano'] = (int) $dt->format('Y');
            $this->model->atualizar($id, $payload);
            return ['success' => true, 'id' => $id];
        }

        $payload['numero'] = null;
        $payload['ano'] = (int) $dt->format('Y');
        $payload['status'] = 'rascunho';
        $payload['criado_por'] = $usuarioId > 0 ? $usuarioId : null;
        $novoId = $this->model->criar($payload);
        if ($novoId <= 0) {
            return ['success' => false, 'error' => 'Não foi possível gravar o ofício.'];
        }
        return ['success' => true, 'id' => $novoId];
    }

    /**
     * @return array{success:bool,error?:string,oficio?:array<string,mixed>}
     */
    public function emitir(int $id): array
    {
        $oficio = $this->model->findById($id);
        if (!$oficio) {
            return ['success' => false, 'error' => 'Ofício não encontrado.'];
        }
        $status = (string) ($oficio['status'] ?? '');
        if ($status === 'cancelado') {
            return ['success' => false, 'error' => 'Ofício cancelado não pode ser emitido.'];
        }
        if ($status === 'emitido' && (int) ($oficio['numero'] ?? 0) > 0) {
            return ['success' => true, 'oficio' => $oficio];
        }

        $ano = (int) ($oficio['ano'] ?? date('Y'));
        if ($ano <= 0) {
            $ano = (int) date('Y');
        }
        $tentativas = 0;
        while ($tentativas < 5) {
            $tentativas++;
            $numero = $this->model->proximoNumero($ano);
            try {
                $this->model->marcarEmitido($id, $numero, $ano);
                $atual = $this->model->findById($id);
                return ['success' => true, 'oficio' => is_array($atual) ? $atual : $oficio];
            } catch (\Throwable $e) {
                if (!str_contains(strtolower($e->getMessage()), 'duplicate')) {
                    return ['success' => false, 'error' => 'Não foi possível numerar o ofício.'];
                }
            }
        }
        return ['success' => false, 'error' => 'Não foi possível obter o próximo número do ofício.'];
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function cancelar(int $id): array
    {
        $oficio = $this->model->findById($id);
        if (!$oficio) {
            return ['success' => false, 'error' => 'Ofício não encontrado.'];
        }
        if ((string) ($oficio['status'] ?? '') === 'cancelado') {
            return ['success' => true];
        }
        $this->model->marcarCancelado($id);
        return ['success' => true];
    }

    /**
     * @param array<string,mixed> $oficio
     * @param array<string,mixed>|null $config
     */
    public function emitirPdf(array $oficio, ?array $config): void
    {
        $pdf = new VidaEscolarPdfService();
        $numero = (int) ($oficio['numero'] ?? 0);
        $ano = (int) ($oficio['ano'] ?? date('Y'));
        $nome = $numero > 0
            ? 'oficio_' . $numero . '_' . $ano . '.pdf'
            : 'oficio_rascunho_' . (int) ($oficio['id'] ?? 0) . '.pdf';
        $pdf->emitirOficio($oficio, $config, $nome);
    }

    private function limite(string $valor, int $max): ?string
    {
        if ($valor === '') {
            return null;
        }
        if (mb_strlen($valor) > $max) {
            $valor = mb_substr($valor, 0, $max);
        }
        return $valor;
    }
}
