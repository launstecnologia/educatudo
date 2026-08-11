<?php

namespace App\AI;

require_once __DIR__ . '/Agentes/Tudinha/MontadorContextoTudinhaAgent.php';
require_once __DIR__ . '/Agentes/Tudinha/GeradorRespostaTudinhaAgent.php';
require_once __DIR__ . '/Agentes/Tudinha/PosProcessadorTudinhaAgent.php';
require_once __DIR__ . '/Agentes/Tudinha/MontadorInstrucoesVozAgent.php';
require_once __DIR__ . '/Agentes/Tudinha/CriadorSessaoRealtimeAgent.php';

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
            new Agentes\Tudinha\MontadorContextoTudinhaAgent(),
            new Agentes\Tudinha\GeradorRespostaTudinhaAgent(),
            new Agentes\Tudinha\PosProcessadorTudinhaAgent(),
        ];
    }

    /**
     * @return AgenteIAInterface[]
     */
    public static function agentesVoz(): array
    {
        return [
            new Agentes\Tudinha\MontadorInstrucoesVozAgent(),
            new Agentes\Tudinha\CriadorSessaoRealtimeAgent(),
        ];
    }
}
