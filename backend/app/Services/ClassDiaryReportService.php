<?php

require_once __DIR__ . '/../Models/Education/ClassDiary.php';
require_once __DIR__ . '/ClassDiaryService.php';

/**
 * EducaTudo - ClassDiaryReportService
 *
 * Monta os datasets dos relatórios do Diário de Classe (indicadores com
 * semáforo, pendências e carga horária) e serializa para CSV/Excel. O PDF é
 * renderizado pelo controller (template HTML + Dompdf), reaproveitando o mesmo
 * mecanismo das declarações oficiais.
 */
class ClassDiaryReportService
{
    /** @var ClassDiary */
    private $diary;

    /** @var ClassDiaryService */
    private $service;

    public function __construct(?ClassDiary $diary = null, ?ClassDiaryService $service = null)
    {
        $this->diary = $diary ?? new ClassDiary();
        $this->diary->ensureSchema();
        $this->service = $service ?? new ClassDiaryService($this->diary);
    }

    /**
     * Conjunto completo de dados para o relatório do diário no período.
     *
     * @return array{periodo:array{inicio:string,fim:string},indicadores:list<array<string,mixed>>,resumo:array<string,mixed>,pendencias:list<array<string,mixed>>}
     */
    public function dados(string $inicio, string $fim, int $professorId = 0): array
    {
        $indicadores = $this->service->indicadores($inicio, $fim, $professorId);
        return [
            'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            'indicadores' => $indicadores,
            'resumo' => $this->service->resumoIndicadores($indicadores),
            'pendencias' => $this->diary->aulasPendentes($inicio, $fim, $professorId),
        ];
    }

    /**
     * Serializa os indicadores em CSV (separador ";", BOM UTF-8 para Excel pt-BR).
     *
     * @param list<array<string,mixed>> $indicadores
     */
    public function indicadoresCsv(array $indicadores): string
    {
        $linhas = [];
        $linhas[] = [
            'Professor', 'Turma', 'Matéria', 'Aulas previstas', 'Aulas ministradas',
            'Pendentes vencidas', 'Cobertura (%)', 'Horas previstas', 'Horas ministradas',
            'Última atualização', 'Situação',
        ];
        foreach ($indicadores as $i) {
            $linhas[] = [
                (string) ($i['professor_nome'] ?? ''),
                (string) ($i['turma_nome'] ?? ''),
                (string) ($i['materia_nome'] ?? ''),
                (string) ((int) ($i['aulas_previstas'] ?? 0)),
                (string) ((int) ($i['aulas_ministradas'] ?? 0)),
                (string) ((int) ($i['pendentes_vencidas'] ?? 0)),
                $i['percentual'] !== null ? number_format((float) $i['percentual'], 1, ',', '') : '-',
                number_format(((int) ($i['minutos_previstos'] ?? 0)) / 60, 1, ',', ''),
                number_format(((int) ($i['minutos_ministrados'] ?? 0)) / 60, 1, ',', ''),
                $this->formatarData((string) ($i['ultima_data'] ?? '')),
                ClassDiaryService::situacaoLabel((string) ($i['situacao'] ?? '')),
            ];
        }

        $csv = "\xEF\xBB\xBF";
        foreach ($linhas as $linha) {
            $csv .= implode(';', array_map([$this, 'escaparCsv'], $linha)) . "\r\n";
        }
        return $csv;
    }

    private function escaparCsv(string $valor): string
    {
        if (preg_match('/[";\r\n]/', $valor)) {
            return '"' . str_replace('"', '""', $valor) . '"';
        }
        return $valor;
    }

    private function formatarData(string $data): string
    {
        if ($data === '') {
            return '-';
        }
        $ts = strtotime($data);
        return $ts !== false ? date('d/m/Y H:i', $ts) : '-';
    }
}
