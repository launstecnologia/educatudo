<?php

namespace App\Agentes;


/**
 * EducaTudo - listas canônicas de agentes do chat Tudinha (texto e voz).
 */
class TudinhaChatPipeline
{
    /**
     * @return AgenteIAInterface[]
     */
    public static function agentesChat(): array
    {
        return [
            new Tudinha\MontadorContextoTudinhaAgent(),
            new Tudinha\GeradorRespostaTudinhaAgent(),
            new Tudinha\PosProcessadorTudinhaAgent(),
        ];
    }

    /**
     * @return AgenteIAInterface[]
     */
    public static function agentesVoz(): array
    {
        return [
            new Tudinha\MontadorInstrucoesVozAgent(),
            new Tudinha\CriadorSessaoRealtimeAgent(),
        ];
    }
}

if (!class_exists('TudinhaChatPipeline', false) && class_exists(__NAMESPACE__ . '\\TudinhaChatPipeline', false)) {
    class_alias(__NAMESPACE__ . '\\TudinhaChatPipeline', 'TudinhaChatPipeline');
}
