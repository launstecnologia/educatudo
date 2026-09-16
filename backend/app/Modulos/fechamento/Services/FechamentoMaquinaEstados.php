<?php
/**
 * Transições canônicas do fechamento por turma × período.
 * Sem I/O — testável sem banco.
 */
class FechamentoMaquinaEstados
{
    public const ABERTO = 'ABERTO';
    public const EM_FECHAMENTO = 'EM_FECHAMENTO';
    public const EM_RECUPERACAO = 'EM_RECUPERACAO';
    public const HOMOLOGADO = 'HOMOLOGADO';
    public const RETIFICADO = 'RETIFICADO';

    public const STATUS = [
        self::ABERTO => 'Aberto',
        self::EM_FECHAMENTO => 'Em fechamento',
        self::EM_RECUPERACAO => 'Em recuperação',
        self::HOMOLOGADO => 'Homologado',
        self::RETIFICADO => 'Retificado',
    ];

    /**
     * Destinos permitidos a partir de cada estado.
     * HOMOLOGADO só vai para RETIFICADO (nunca volta a ABERTO/EM_FECHAMENTO).
     *
     * @var array<string, list<string>>
     */
    public const TRANSICOES = [
        self::ABERTO => [self::EM_FECHAMENTO],
        self::EM_FECHAMENTO => [self::ABERTO, self::EM_RECUPERACAO, self::HOMOLOGADO],
        self::EM_RECUPERACAO => [self::EM_FECHAMENTO, self::HOMOLOGADO],
        self::HOMOLOGADO => [self::RETIFICADO],
        self::RETIFICADO => [self::EM_FECHAMENTO, self::EM_RECUPERACAO, self::HOMOLOGADO],
    ];

    public static function normalizar(string $status): string
    {
        $status = strtoupper(trim($status));
        return isset(self::STATUS[$status]) ? $status : self::ABERTO;
    }

    public static function rotulo(string $status): string
    {
        $status = self::normalizar($status);
        return self::STATUS[$status];
    }

    public static function podeTransitar(string $de, string $para): bool
    {
        $de = self::normalizar($de);
        $para = self::normalizar($para);
        if ($de === $para) {
            return true;
        }
        return in_array($para, self::TRANSICOES[$de] ?? [], true);
    }

    /**
     * Homologado trava edição oficial. Retificado reabre com auditoria.
     */
    public static function estaTravado(string $status): bool
    {
        return self::normalizar($status) === self::HOMOLOGADO;
    }

    public static function exigeJustificativa(string $de, string $para): bool
    {
        $de = self::normalizar($de);
        $para = self::normalizar($para);
        return $de === self::HOMOLOGADO && $para === self::RETIFICADO;
    }

    public static function periodoRef(int $anoLetivo, string $periodoTipo, int $periodoNumero): string
    {
        $anoLetivo = (int) $anoLetivo;
        $periodoTipo = strtolower(trim($periodoTipo));
        $periodoNumero = (int) $periodoNumero;
        if ($periodoTipo === 'bimestre' && $periodoNumero >= 1 && $periodoNumero <= 4) {
            return $anoLetivo . '-B' . $periodoNumero;
        }
        if ($periodoTipo === 'trimestre' && $periodoNumero >= 1 && $periodoNumero <= 3) {
            return $anoLetivo . '-T' . $periodoNumero;
        }
        if ($periodoTipo === 'semestre' && $periodoNumero >= 1 && $periodoNumero <= 2) {
            return $anoLetivo . '-S' . $periodoNumero;
        }
        return $anoLetivo . '-ANO';
    }

    public static function mensagemTransicaoInvalida(string $de, string $para): string
    {
        $de = self::normalizar($de);
        $para = self::normalizar($para);
        if ($de === self::HOMOLOGADO && $para !== self::RETIFICADO) {
            return 'Período homologado não pode ser reaberto. Use o fluxo formal de retificação com justificativa.';
        }
        return 'Transição de ' . self::rotulo($de) . ' para ' . self::rotulo($para) . ' não é permitida.';
    }
}
