<?php
/**
 * Notas e frequência determinísticas com cara de escola real.
 * Idempotente: o mesmo aluno/matéria/bimestre sempre gera o mesmo valor.
 */
declare(strict_types=1);

final class SimulacaoAcademicaEduca
{
    /**
     * @return 'excelente'|'regular'|'irregular'|'faltoso'|'recuperacao'|'risco'
     */
    public static function perfil(int $alunoId, ?string $cenarioSlot = null): string
    {
        $cenarioSlot = strtolower(trim((string) $cenarioSlot));
        if ($cenarioSlot === 'reprovado') {
            return 'risco';
        }
        if ($cenarioSlot === 'recuperacao') {
            return 'recuperacao';
        }
        if ($cenarioSlot === 'frequencia') {
            return 'faltoso';
        }
        $slot = abs($alunoId) % 20;
        if ($slot <= 1) {
            return 'excelente';
        }
        if ($slot <= 13) {
            return 'regular';
        }
        if ($slot <= 16) {
            return 'irregular';
        }
        if ($slot === 17) {
            return 'faltoso';
        }
        if ($slot === 18) {
            return 'recuperacao';
        }
        return 'risco';
    }

    public static function nota(int $alunoId, int $materiaId, int $bimestre, string $tipoEv = 'p1', ?string $cenarioSlot = null): float
    {
        $perfil = self::perfil($alunoId, $cenarioSlot);
        $bimestre = max(1, min(4, $bimestre));
        $tipoEv = strtolower(trim($tipoEv));

        $base = match ($perfil) {
            'excelente' => 8.7,
            'regular' => 7.5,
            'irregular' => 6.9,
            'faltoso' => 6.8,
            'recuperacao' => 6.3,
            'risco' => 5.1,
        };

        $afinidade = self::unit($alunoId, $materiaId, 11) * 2.2 - 0.9;
        $n = $base + $afinidade;

        $n += match ($bimestre) {
            1 => -0.25,
            2 => 0.0,
            3 => 0.2,
            default => 0.15,
        };

        if (self::materiaFraca($alunoId, $materiaId)) {
            if ($perfil === 'recuperacao') {
                $n = 5.1 + self::unit($alunoId, $materiaId, $bimestre, 3) * 1.1;
                if ($bimestre >= 3) {
                    $n += 1.5;
                }
            } elseif ($perfil === 'risco') {
                $n = 3.4 + self::unit($alunoId, $materiaId, $bimestre, 5) * 1.6;
            } elseif ($perfil === 'irregular') {
                $n -= 0.8;
            }
        }

        if ($tipoEv === 'p2') {
            $n += 0.15 + self::unit($alunoId, $materiaId, $bimestre, 7) * 0.4 - 0.15;
        } elseif ($tipoEv === 'atv') {
            $n += self::unit($alunoId, $materiaId, $bimestre, 9) * 1.1 - 0.35;
        } elseif ($tipoEv === 'trab' || $tipoEv === 'trabalho') {
            $n += self::unit($alunoId, $materiaId, $bimestre, 11) * 0.8 - 0.15;
        } elseif ($tipoEv === 'rec') {
            $n = max($n, 6.2 + self::unit($alunoId, $materiaId, 13) * 1.4);
        }

        if ($perfil !== 'excelente' && self::unit($alunoId, $materiaId, $bimestre, 17) < 0.02) {
            $n -= 1.3;
        }
        if ($perfil === 'excelente' && self::unit($alunoId, $materiaId, $bimestre, 19) < 0.08) {
            $n = min(10.0, $n + 0.8);
        }

        return round(max(1.0, min(10.0, $n)), 1);
    }

    public static function situacaoFrequencia(
        int $alunoId,
        int $materiaId,
        int $bimestre,
        string $data,
        ?string $cenarioSlot = null
    ): string {
        $perfil = self::perfil($alunoId, $cenarioSlot);
        $pFalta = match ($perfil) {
            'excelente' => 0.015,
            'regular' => 0.055,
            'irregular' => 0.10,
            'faltoso' => 0.18,
            'recuperacao' => 0.08,
            'risco' => 0.16,
        };
        $pJust = $pFalta * 0.25;
        $pAtraso = match ($perfil) {
            'excelente' => 0.02,
            'faltoso' => 0.08,
            default => 0.035,
        };

        $u = self::unit($alunoId, $materiaId, crc32($data) & 0x7fffffff, $bimestre);
        if ($u < $pFalta) {
            return 'falta';
        }
        if ($u < $pFalta + $pJust) {
            return 'falta_justificada';
        }
        if ($u < $pFalta + $pJust + $pAtraso) {
            return 'atraso';
        }
        return 'presente';
    }

    public static function tipoDoTitulo(string $titulo): string
    {
        if (stripos($titulo, 'Prova Bimestral 2') !== false || preg_match('/\bP2\b/i', $titulo)) {
            return 'p2';
        }
        if (stripos($titulo, 'Trabalho') !== false) {
            return 'trab';
        }
        if (stripos($titulo, 'Atividade') !== false) {
            return 'atv';
        }
        if (stripos($titulo, 'Recupera') !== false) {
            return 'rec';
        }
        return 'p1';
    }

    private static function materiaFraca(int $alunoId, int $materiaId): bool
    {
        if ($materiaId <= 0) {
            return false;
        }
        $slot = abs($alunoId + $materiaId * 3) % 11;
        return $slot <= 1;
    }

    private static function unit(int ...$parts): float
    {
        $hash = crc32(implode(':', $parts));
        return ($hash & 0x7fffffff) / 2147483647.0;
    }
}
