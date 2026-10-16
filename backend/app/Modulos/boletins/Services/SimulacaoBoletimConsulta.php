<?php

namespace App\Modulos\Boletins\Services;

/**
 * Decide o período e a coerência da simulação.
 * O cálculo das notas continua em BoletimConfigController::simularRegraAluno.
 */
class SimulacaoBoletimConsulta
{
    /**
     * @param array<string,mixed> $regra
     * @return array{tipo:string,inicio:string,fim:string,ano:int,bimestre:int,mensagem:string}
     */
    public static function resolverPeriodo(array $regra, ?string $inicioInformado, ?string $fimInformado): array
    {
        $inicio = self::dataValida($inicioInformado);
        $fim = self::dataValida($fimInformado);
        if ($inicio !== '' && $fim !== '') {
            if ($inicio > $fim) {
                return self::periodo('indefinido', '', '', 0, 0, 'A data final da simulação é anterior à data inicial.');
            }

            return self::periodo('datas', $inicio, $fim, (int) substr($inicio, 0, 4), (int) ($regra['bimestre'] ?? 0), '');
        }

        $inicioRegra = self::dataValida(isset($regra['default_data_inicio']) ? (string) $regra['default_data_inicio'] : null);
        $fimRegra = self::dataValida(isset($regra['default_data_fim']) ? (string) $regra['default_data_fim'] : null);
        if ($inicioRegra !== '' && $fimRegra !== '' && $inicioRegra <= $fimRegra) {
            return self::periodo('datas', $inicioRegra, $fimRegra, (int) substr($inicioRegra, 0, 4), (int) ($regra['bimestre'] ?? 0), '');
        }

        $ano = (int) ($regra['ano_letivo'] ?? 0);
        $bimestre = (int) ($regra['bimestre'] ?? 0);
        if ($ano >= 2000 && $ano <= 2100 && $bimestre >= 1 && $bimestre <= 4) {
            return self::periodo('bimestre', '', '', $ano, $bimestre, '');
        }
        if ($ano >= 2000 && $ano <= 2100) {
            return self::periodo('ano', '', '', $ano, 0, '');
        }

        return self::periodo(
            'indefinido',
            '',
            '',
            0,
            0,
            'Este período ainda não tem ano letivo ou bimestre para simular.'
        );
    }

    /**
     * @param array<string,mixed>|null $modelo
     * @param array<string,mixed>|null $regra
     */
    public static function inconsistencia(?array $modelo, ?array $regra): ?string
    {
        $regraId = (int) ($regra['id'] ?? 0);
        if (!is_array($regra) || $regraId <= 0) {
            return 'Selecione uma avaliação para simular.';
        }

        $modeloId = (int) ($modelo['id'] ?? 0);
        $boletimDaRegra = (int) ($regra['boletim_id'] ?? 0);
        if ($modeloId > 0 && $boletimDaRegra !== $modeloId) {
            return 'Esta avaliação não pertence a este modelo de boletim.';
        }

        $anoModelo = (int) ($modelo['ano_letivo'] ?? 0);
        $anoRegra = (int) ($regra['ano_letivo'] ?? 0);
        if ($anoModelo >= 2000 && $anoRegra >= 2000 && $anoModelo !== $anoRegra) {
            return 'O ano da avaliação (' . $anoRegra . ') não é o ano deste modelo (' . $anoModelo . ').';
        }

        return null;
    }

    public static function rotuloPeriodo(int $ano, int $bimestre, string $inicio, string $fim): string
    {
        $texto = '';
        if ($bimestre >= 1 && $bimestre <= 4 && $ano >= 2000) {
            $texto = $bimestre . 'º bimestre de ' . $ano;
        } elseif ($ano >= 2000) {
            $texto = 'Ano letivo ' . $ano;
        }
        if ($inicio !== '' && $fim !== '') {
            $intervalo = self::dataBr($inicio) . ' a ' . self::dataBr($fim);
            $texto = $texto === '' ? $intervalo : ($texto . ' (' . $intervalo . ')');
        }

        return $texto;
    }

    /**
     * @return array{tipo:string,inicio:string,fim:string,ano:int,bimestre:int,mensagem:string}
     */
    private static function periodo(string $tipo, string $inicio, string $fim, int $ano, int $bimestre, string $mensagem): array
    {
        return [
            'tipo' => $tipo,
            'inicio' => $inicio,
            'fim' => $fim,
            'ano' => $ano,
            'bimestre' => $bimestre,
            'mensagem' => $mensagem,
        ];
    }

    private static function dataValida(?string $valor): string
    {
        $valor = trim((string) $valor);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m)) {
            return '';
        }
        $ano = (int) $m[1];
        $mes = (int) $m[2];
        $dia = (int) $m[3];
        if ($ano < 2000 || $ano > 2100 || !checkdate($mes, $dia, $ano)) {
            return '';
        }

        return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
    }

    private static function dataBr(string $ymd): string
    {
        $partes = explode('-', $ymd);

        return count($partes) === 3 ? ($partes[2] . '/' . $partes[1] . '/' . $partes[0]) : $ymd;
    }
}
