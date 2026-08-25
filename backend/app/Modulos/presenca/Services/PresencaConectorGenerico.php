<?php

/**
 * Normaliza payloads de catraca (webhook genérico e aliases comuns de fornecedores).
 */
class PresencaConectorGenerico
{
    /**
     * @param array<string,mixed> $payload
     * @return array{id_externo:string,tipo:string,ocorrido_em:string,identificador:string,tipo_identificador:?string,aluno_id:int}
     */
    public function normalizar(array $payload): array
    {
        $idExterno = trim((string) (
            $payload['id_externo']
            ?? $payload['event_id']
            ?? $payload['id']
            ?? $payload['uuid']
            ?? ''
        ));
        if ($idExterno === '' || mb_strlen($idExterno) > 160) {
            throw new InvalidArgumentException('Informe id_externo (ou event_id) do evento.');
        }

        $tipoRaw = strtolower(trim((string) (
            $payload['tipo'] ?? $payload['kind'] ?? $payload['direction'] ?? $payload['event_type'] ?? ''
        )));
        $tipoMap = [
            'entrada' => 'entrada',
            'in' => 'entrada',
            'entry' => 'entrada',
            'checkin' => 'entrada',
            'check_in' => 'entrada',
            'saida' => 'saida',
            'saída' => 'saida',
            'out' => 'saida',
            'exit' => 'saida',
            'checkout' => 'saida',
            'check_out' => 'saida',
        ];
        $tipo = $tipoMap[$tipoRaw] ?? '';
        if ($tipo === '') {
            throw new InvalidArgumentException('Informe tipo entrada ou saida.');
        }

        $quandoRaw = trim((string) (
            $payload['ocorrido_em'] ?? $payload['event_at'] ?? $payload['timestamp'] ?? $payload['data_hora'] ?? ''
        ));
        try {
            $quando = $quandoRaw !== '' ? new DateTimeImmutable($quandoRaw) : new DateTimeImmutable('now');
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Data e horário inválidos.');
        }

        $alunoId = (int) ($payload['aluno_id'] ?? $payload['student_id'] ?? 0);
        $identificador = trim((string) (
            $payload['identificador']
            ?? $payload['card_id']
            ?? $payload['cartao']
            ?? $payload['ra']
            ?? $payload['codigo_aluno']
            ?? $payload['external_id']
            ?? ''
        ));
        $tipoIdent = $payload['tipo_identificador'] ?? $payload['identifier_type'] ?? null;
        $tipoIdent = is_string($tipoIdent) && $tipoIdent !== '' ? strtolower($tipoIdent) : null;

        return [
            'id_externo' => $idExterno,
            'tipo' => $tipo,
            'ocorrido_em' => $quando->format('Y-m-d H:i:s'),
            'identificador' => $identificador,
            'tipo_identificador' => $tipoIdent,
            'aluno_id' => $alunoId,
        ];
    }
}
