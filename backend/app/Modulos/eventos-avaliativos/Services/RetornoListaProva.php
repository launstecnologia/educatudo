<?php

namespace App\Modulos\EventosAvaliativos\Services;

use App\Core\Database;
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
        $url = self::montarUrlEventos($ano, $periodo, $regra);
        if (!isset($_SESSION[self::SESSAO]) || !is_array($_SESSION[self::SESSAO])) {
            $_SESSION[self::SESSAO] = [];
        }
        $_SESSION[self::SESSAO][$chave] = $url;
    }

    public static function endereco(int $blocoId): string
    {
        $padraoProvas = URL . '/admin/provas';
        if (!LayoutHelper::isModuleEnabled('eventos_avaliativos')) {
            return $padraoProvas;
        }

        if ($blocoId > 0) {
            $mapa = $_SESSION[self::SESSAO] ?? null;
            $salva = is_array($mapa) ? ($mapa[self::chave($blocoId)] ?? '') : '';
            if (is_string($salva) && self::urlEventosValida($salva)) {
                return URL . $salva;
            }

            $doEvento = self::urlDoEventoDoBloco($blocoId);
            if ($doEvento !== '') {
                return URL . $doEvento;
            }
        }

        return URL . '/admin/eventos-avaliativos';
    }

    private static function montarUrlEventos(int $ano, int $periodo, int $regra = 0): string
    {
        $url = '/admin/eventos-avaliativos';
        if ($ano >= 2000 && $ano <= 2100 && $periodo > 0 && $periodo < 200) {
            $url .= '?ano=' . $ano . '&periodo=' . $periodo;
            if ($regra > 0) {
                $url .= '&regra=' . $regra;
            }
        }

        return $url;
    }

    private static function urlEventosValida(string $salva): bool
    {
        return (bool) preg_match(
            '#^/admin/eventos-avaliativos(?:\?ano=\d{4}&periodo=\d{1,3}(?:&regra=\d+)?)?$#',
            $salva
        );
    }

    /**
     * Quando não há sessão (ou o usuário entrou por outro caminho), monta o Voltar
     * a partir do evento avaliativo vinculado ao bloco.
     */
    private static function urlDoEventoDoBloco(int $blocoId): string
    {
        if ($blocoId <= 0) {
            return '';
        }
        try {
            $db = Database::getInstance();
            $row = $db->fetch(
                'SELECT e.ano_letivo, e.bimestre
                   FROM provas_blocos pb
                   INNER JOIN eventos_avaliativos e ON e.id = pb.evento_avaliativo_id
                        AND e.deleted_at IS NULL
                  WHERE pb.id = :id AND pb.deleted_at IS NULL
                  LIMIT 1',
                ['id' => $blocoId]
            );
            if (!is_array($row)) {
                return '/admin/eventos-avaliativos';
            }

            return self::montarUrlEventos(
                (int) ($row['ano_letivo'] ?? 0),
                (int) ($row['bimestre'] ?? 0),
                0
            );
        } catch (\Throwable $e) {
            return '/admin/eventos-avaliativos';
        }
    }

    private static function chave(int $blocoId): string
    {
        $tenant = defined('TENANT_ID') ? (int) TENANT_ID : 0;

        return $tenant . ':' . $blocoId;
    }
}
