<?php

namespace App\Modulos\DashboardGestao\Widgets;

use App\Modulos\DashboardGestao\Services\DashboardConsulta;
use App\Modulos\DashboardGestao\Services\DashboardFiltro;

class CalendarioWidget implements WidgetDashboard
{
    private DashboardConsulta $consulta;

    public function __construct(DashboardConsulta $consulta)
    {
        $this->consulta = $consulta;
    }

    public function chave(): string
    {
        return 'calendario';
    }

    public function montar(DashboardFiltro $filtro): array
    {
        $eventos = array_merge(
            $this->eventosEscolares($filtro),
            $this->eventosLetivos($filtro),
            $this->provasProximas($filtro)
        );
        usort($eventos, static fn ($a, $b) => strcmp((string) $a['data'], (string) $b['data']));
        $eventos = array_slice($eventos, 0, 8);

        return [
            'ok' => true,
            'disponivel' => true,
            'eventos' => $eventos,
            'href_escolar' => '/admin/calendario-escolar',
            'href_letivo' => '/admin/calendario-letivo',
        ];
    }

    /**
     * @return list<array{data:string,titulo:string,tipo:string}>
     */
    private function eventosEscolares(DashboardFiltro $filtro): array
    {
        if (!$this->consulta->tabelaExiste('school_calendar_events')) {
            return [];
        }
        $db = \Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT titulo, DATE(inicio_em) AS data_evento, categoria
             FROM school_calendar_events
             WHERE status = 'publicado' AND DATE(inicio_em) >= :hoje
             ORDER BY inicio_em ASC
             LIMIT 8",
            ['hoje' => $filtro->hoje]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'data' => (string) ($row['data_evento'] ?? ''),
                'titulo' => (string) ($row['titulo'] ?? 'Evento'),
                'tipo' => $this->tipoCategoria((string) ($row['categoria'] ?? 'evento')),
            ];
        }
        return $out;
    }

    /**
     * @return list<array{data:string,titulo:string,tipo:string}>
     */
    private function eventosLetivos(DashboardFiltro $filtro): array
    {
        if (!$this->consulta->tabelaExiste('calendario_letivo_eventos')) {
            return [];
        }
        $db = \Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT e.descricao, e.data_inicio, e.tipo
             FROM calendario_letivo_eventos e
             INNER JOIN calendario_letivo c ON c.id = e.calendario_id
             WHERE c.ano = :ano AND e.data_fim >= :hoje
             ORDER BY e.data_inicio ASC
             LIMIT 8",
            ['ano' => $filtro->anoCivil, 'hoje' => $filtro->hoje]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'data' => (string) ($row['data_inicio'] ?? ''),
                'titulo' => (string) ($row['descricao'] ?? 'Evento letivo'),
                'tipo' => $this->tipoLetivo((string) ($row['tipo'] ?? 'evento')),
            ];
        }
        return $out;
    }

    /**
     * @return list<array{data:string,titulo:string,tipo:string}>
     */
    private function provasProximas(DashboardFiltro $filtro): array
    {
        if (!$this->consulta->tabelaExiste('provas_blocos')) {
            return [];
        }
        $db = \Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT titulo, DATE(data_prova) AS data_prova
             FROM provas_blocos
             WHERE deleted_at IS NULL AND DATE(data_prova) >= :hoje
             ORDER BY data_prova ASC
             LIMIT 5",
            ['hoje' => $filtro->hoje]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'data' => (string) ($row['data_prova'] ?? ''),
                'titulo' => (string) ($row['titulo'] ?? 'Avaliação'),
                'tipo' => 'Prova',
            ];
        }
        return $out;
    }

    private function tipoCategoria(string $cat): string
    {
        $map = [
            'prova' => 'Prova',
            'avaliacao' => 'Prova',
            'conselho' => 'Conselho',
            'recuperacao' => 'Recuperação',
            'feriado' => 'Feriado',
            'evento' => 'Evento escolar',
        ];
        return $map[strtolower($cat)] ?? 'Evento escolar';
    }

    private function tipoLetivo(string $tipo): string
    {
        $map = [
            'feriado' => 'Feriado',
            'recesso' => 'Recesso',
            'avaliacao' => 'Prova',
            'evento' => 'Evento escolar',
            'reposicao' => 'Reposição',
            'suspensao' => 'Suspensão',
        ];
        try {
            require_once dirname(__DIR__, 3) . '/Services/SchoolCalendarService.php';
            $service = new \SchoolCalendarService();
            foreach ($service->tipos() as $slug => $t) {
                $map[$slug] = (string) ($t['nome'] ?? $slug);
            }
        } catch (\Throwable $e) {
            // fallback nos rótulos padrão
        }
        return $map[$tipo] ?? 'Evento escolar';
    }
}
