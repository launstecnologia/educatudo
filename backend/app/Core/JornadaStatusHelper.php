<?php
/**
 * Recalcula o status da jornada (aguardando / em_andamento / concluido) com base em data e hora
 * e persiste no banco quando diferente do armazenado. Assim o status é corrigido ao carregar
 * a lista ou a tela da jornada, sem depender apenas do CRON.
 */
class JornadaStatusHelper
{
    /**
     * Recalcula status sem persistir no banco (uso em listagens para evitar escrita em massa).
     *
     * @param string $estruturaJson JSON da coluna estrutura
     * @return string 'aguardando' | 'em_andamento' | 'concluido'
     */
    public static function recalcularSemPersistir(string $estruturaJson): string
    {
        $estrutura = json_decode($estruturaJson, true) ?: [];
        return self::calcularStatus($estrutura);
    }

    /**
     * Calcula o status da jornada com base na data/hora atual e nas datas da estrutura.
     * Usa hora_inicio e hora_fim quando existirem.
     *
     * @param array $estrutura Decoded JSON da coluna estrutura (com data_inicio, data_fim, hora_inicio, hora_fim)
     * @return string 'aguardando' | 'em_andamento' | 'concluido'
     */
    public static function calcularStatus(array $estrutura): string
    {
        $dataInicio = $estrutura['data_inicio'] ?? null;
        $dataFim = $estrutura['data_fim'] ?? null;
        if (!$dataInicio || !$dataFim) {
            return 'em_andamento';
        }

        $horaInicio = trim((string)($estrutura['hora_inicio'] ?? '')) ?: '00:00';
        $horaFim = trim((string)($estrutura['hora_fim'] ?? '')) ?: '23:59:59';
        $tsInicio = strtotime($dataInicio . ' ' . $horaInicio);
        $tsFim = strtotime($dataFim . ' ' . $horaFim);

        if ($tsInicio === false || $tsFim === false) {
            $dataAtual = date('Y-m-d');
            if ($dataAtual < $dataInicio) {
                return 'aguardando';
            }
            if ($dataAtual > $dataFim) {
                return 'concluido';
            }
            return 'em_andamento';
        }

        $tsNow = time();
        if ($tsNow < $tsInicio) {
            return 'aguardando';
        }
        if ($tsNow > $tsFim) {
            return 'concluido';
        }
        return 'em_andamento';
    }

    /**
     * Recalcula o status da jornada e, se diferente do armazenado, atualiza a tabela jornadas.
     * Pode ser chamado ao listar ou abrir uma jornada para corrigir o status sem depender do CRON.
     *
     * @param \Database $db Instância do banco
     * @param int $jornadaId ID da jornada
     * @param string $estruturaJson JSON da coluna estrutura
     * @param string|null $statusColunaAtual Valor atual da coluna status (ativa, pausada, finalizada)
     * @return string Status calculado ('aguardando' | 'em_andamento' | 'concluido')
     */
    public static function recalcularEPersistir($db, int $jornadaId, string $estruturaJson, ?string $statusColunaAtual = null): string
    {
        $estrutura = json_decode($estruturaJson, true) ?: [];
        $statusCalculado = self::calcularStatus($estrutura);
        $statusAnterior = $estrutura['status_jornada'] ?? null;
        $statusColuna = $statusCalculado === 'concluido' ? 'finalizada' : $statusCalculado;
        $statusColunaAtualNorm = strtolower(trim((string)$statusColunaAtual));
        $pausada = ($statusColunaAtualNorm === 'pausada');

        // Atualiza quando o JSON está desatualizado OU quando a coluna está desatualizada (ex.: ainda "aguardando" após passar a data)
        $atualizarJson = ($statusAnterior !== $statusCalculado);
        $atualizarColuna = !$pausada && ($statusColunaAtualNorm !== $statusColuna);

        if (!$atualizarJson && !$atualizarColuna) {
            return $statusCalculado;
        }

        if ($atualizarJson) {
            $estrutura['status_jornada'] = $statusCalculado;
        }
        $estruturaAtualizada = json_encode($estrutura, JSON_UNESCAPED_UNICODE);

        if ($pausada) {
            $db->update(
                "UPDATE jornadas SET estrutura = :estrutura, updated_at = NOW() WHERE id = :id",
                ['estrutura' => $estruturaAtualizada, 'id' => $jornadaId]
            );
        } elseif ($atualizarColuna) {
            $db->update(
                "UPDATE jornadas SET estrutura = :estrutura, status = :status, updated_at = NOW() WHERE id = :id",
                [
                    'estrutura' => $estruturaAtualizada,
                    'status' => $statusColuna,
                    'id' => $jornadaId
                ]
            );
        } else {
            $db->update(
                "UPDATE jornadas SET estrutura = :estrutura, updated_at = NOW() WHERE id = :id",
                ['estrutura' => $estruturaAtualizada, 'id' => $jornadaId]
            );
        }

        return $statusCalculado;
    }
}
