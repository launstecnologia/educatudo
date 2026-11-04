<?php

namespace App\Modulos\EventosAvaliativos\Services;

use App\Core\LayoutHelper;

/**
 * Lembra, por bloco, se a coordenação entrou pela lista de Eventos Avaliativos.
 * O Voltar do evento usa esse caminho enquanto o módulo estiver ligado.
 */
class RetornoListaProva
{
    private const SESSAO = 'retorno_lista_prova';

    public static function registrar(int $blocoId): void
    {
        if ($blocoId <= 0) {
            return;
        }
        $de = (string) ($_GET['de'] ?? '');
        $chave = self::chave($blocoId);
        if ($de === 'provas') {
            if (isset($_SESSION[self::SESSAO][$chave])) {
                unset($_SESSION[self::SESSAO][$chave]);
            }
            return;
        }
        if ($de !== 'eventos' || !LayoutHelper::isModuleEnabled('eventos_avaliativos')) {
            return;
        }

        $ano = (int) ($_GET['ano'] ?? 0);
        $periodo = (int) ($_GET['periodo'] ?? 0);
        $regra = (int) ($_GET['regra'] ?? 0);
        $url = '/admin/eventos-avaliativos';
        if ($ano >= 2000 && $ano <= 2100 && $periodo > 0 && $periodo < 100) {
            $url .= '?ano=' . $ano . '&periodo=' . $periodo;
            if ($regra > 0) {
                $url .= '&regra=' . $regra;
            }
        }
        if (!isset($_SESSION[self::SESSAO]) || !is_array($_SESSION[self::SESSAO])) {
            $_SESSION[self::SESSAO] = [];
        }
        $_SESSION[self::SESSAO][$chave] = $url;
    }

    public static function endereco(int $blocoId): string
    {
        $padrao = URL . '/admin/provas';
        if ($blocoId <= 0 || !LayoutHelper::isModuleEnabled('eventos_avaliativos')) {
            return $padrao;
        }
        $mapa = $_SESSION[self::SESSAO] ?? null;
        $salva = is_array($mapa) ? ($mapa[self::chave($blocoId)] ?? '') : '';
        if (!is_string($salva) || !preg_match('#^/admin/eventos-avaliativos(?:\?ano=\d{4}&periodo=\d{1,2}(?:&regra=\d+)?)?$#', $salva)) {
            return $padrao;
        }

        return URL . $salva;
    }

    private static function chave(int $blocoId): string
    {
        $tenant = defined('TENANT_ID') ? (int) TENANT_ID : 0;

        return $tenant . ':' . $blocoId;
    }
}
