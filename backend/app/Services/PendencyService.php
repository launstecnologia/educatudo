<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/ClassDiaryService.php';

/**
 * EducaTudo - PendencyService
 *
 * Central de Pendências: lista, de forma consolidada, tudo o que está em
 * desacordo nos módulos (documentação do aluno, Censo/INEP, diário em atraso),
 * para a coordenação/secretaria resolver antes de auditorias e do Censo Escolar.
 */
class PendencyService
{
    /** @var Database */
    private $db;

    private const DOCS_ESSENCIAIS = [
        'rg' => 'RG',
        'cpf' => 'CPF',
        'certidao_nascimento' => 'Certidão de nascimento',
        'comprovante_residencia' => 'Comprovante de residência',
    ];

    public const AREAS = [
        'documentacao' => 'Documentação',
        'censo' => 'Censo / INEP',
        'diario' => 'Diário de Classe',
        'frequencia' => 'Frequência',
    ];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Lista de pendências (opcionalmente filtrada por área).
     *
     * @return list<array{area:string,area_label:string,titulo:string,descricao:string,link:?string,severidade:string}>
     */
    public function listar(string $area = '', int $limite = 500): array
    {
        $out = [];
        if ($area === '' || $area === 'documentacao') {
            $out = array_merge($out, $this->pendenciasDocumentacao($limite));
        }
        if ($area === '' || $area === 'censo') {
            $out = array_merge($out, $this->pendenciasCenso($limite));
        }
        if ($area === '' || $area === 'diario') {
            $out = array_merge($out, $this->pendenciasDiario());
        }
        if ($area === '' || $area === 'frequencia') {
            $out = array_merge($out, $this->pendenciasFrequencia());
        }
        return array_slice($out, 0, $limite);
    }

    /** @return list<array<string,mixed>> */
    private function pendenciasDocumentacao(int $limite): array
    {
        if (!$this->tableExists('alunos_documentos') || !$this->tableExists('alunos')) {
            return [];
        }
        $tipos = array_keys(self::DOCS_ESSENCIAIS);
        $ph = implode(',', array_fill(0, count($tipos), '?'));
        $rows = $this->db->fetchAll(
            "SELECT a.id, a.nome, t.nome AS turma_nome,
                    GROUP_CONCAT(DISTINCT CASE WHEN d.status = 'entregue' THEN d.tipo END) AS entregues
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             LEFT JOIN alunos_documentos d ON d.aluno_id = a.id AND d.tipo IN ($ph)
             WHERE a.ativo = 1
             GROUP BY a.id, a.nome, t.nome
             LIMIT " . (int) $limite,
            $tipos
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $entregues = array_filter(explode(',', (string) ($r['entregues'] ?? '')));
            $faltando = [];
            foreach (self::DOCS_ESSENCIAIS as $tipo => $label) {
                if (!in_array($tipo, $entregues, true)) {
                    $faltando[] = $label;
                }
            }
            if (!$faltando) {
                continue;
            }
            $out[] = [
                'area' => 'documentacao',
                'area_label' => self::AREAS['documentacao'],
                'titulo' => (string) $r['nome'] . (($r['turma_nome'] ?? '') !== '' ? ' (' . $r['turma_nome'] . ')' : ''),
                'descricao' => 'Documentos faltando: ' . implode(', ', $faltando) . '.',
                'link' => '/admin/students/' . (int) $r['id'],
                'severidade' => count($faltando) >= 3 ? 'alta' : 'media',
            ];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function pendenciasCenso(int $limite): array
    {
        if (!$this->tableExists('alunos')) {
            return [];
        }
        $cols = $this->colunas('alunos');
        $mapa = [
            'codigo_inep' => 'código INEP',
            'cpf' => 'CPF',
            'nome_mae' => 'nome da mãe',
            'cor_raca' => 'cor/raça',
            'data_nasc' => 'data de nascimento',
        ];
        $checks = [];
        foreach ($mapa as $col => $label) {
            if (isset($cols[$col])) {
                $checks[$col] = $label;
            }
        }
        if (!$checks) {
            return [];
        }
        $sel = [];
        foreach ($checks as $col => $label) {
            $sel[] = "(a.$col IS NULL OR TRIM(a.$col) = '') AS falta_$col";
        }
        $rows = $this->db->fetchAll(
            "SELECT a.id, a.nome, t.nome AS turma_nome, " . implode(', ', $sel) . "
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1
             LIMIT " . (int) $limite
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $faltando = [];
            foreach ($checks as $col => $label) {
                if (!empty($r['falta_' . $col])) {
                    $faltando[] = $label;
                }
            }
            if (!$faltando) {
                continue;
            }
            $out[] = [
                'area' => 'censo',
                'area_label' => self::AREAS['censo'],
                'titulo' => (string) $r['nome'] . (($r['turma_nome'] ?? '') !== '' ? ' (' . $r['turma_nome'] . ')' : ''),
                'descricao' => 'Campos do Censo faltando: ' . implode(', ', $faltando) . '.',
                'link' => '/admin/students/' . (int) $r['id'],
                'severidade' => count($faltando) >= 3 ? 'alta' : 'media',
            ];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function pendenciasDiario(): array
    {
        if (!$this->tableExists('diario_aulas') || !$this->tableExists('grade_horaria')) {
            return [];
        }
        try {
            $service = new ClassDiaryService();
            $indicadores = $service->indicadores(date('Y-m-01'), date('Y-m-d'));
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($indicadores as $i) {
            if (($i['situacao'] ?? '') === 'em_dia' || (int) ($i['pendentes_vencidas'] ?? 0) <= 0) {
                continue;
            }
            $out[] = [
                'area' => 'diario',
                'area_label' => self::AREAS['diario'],
                'titulo' => (string) $i['professor_nome'] . ' — ' . $i['turma_nome'] . ' / ' . $i['materia_nome'],
                'descricao' => (int) $i['pendentes_vencidas'] . ' aula(s) prevista(s) sem registro no diário.',
                'link' => '/admin/diario/indicadores',
                'severidade' => ($i['situacao'] ?? '') === 'atraso' ? 'alta' : 'media',
            ];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function pendenciasFrequencia(): array
    {
        if (!$this->tableExists('diario_frequencias') || !$this->tableExists('diario_aulas')) {
            return [];
        }
        require_once __DIR__ . '/FrequencyService.php';
        $freq = new FrequencyService($this->db);
        $turmas = $this->db->fetchAll("SELECT id, nome FROM turmas ORDER BY nome") ?: [];
        $inicio = date('Y-01-01');
        $fim = date('Y-m-d');
        $out = [];
        foreach ($turmas as $turma) {
            $abaixo = $freq->abaixoDoMinimo((int) $turma['id'], $inicio, $fim);
            foreach ($abaixo as $aluno) {
                $out[] = [
                    'area' => 'frequencia',
                    'area_label' => self::AREAS['frequencia'],
                    'titulo' => (string) $aluno['nome'] . ' (' . $turma['nome'] . ')',
                    'descricao' => 'Frequência de ' . number_format((float) $aluno['percentual'], 1, ',', '') . '% — abaixo do mínimo legal de 75%.',
                    'link' => '/admin/students/' . (int) $aluno['aluno_id'],
                    'severidade' => 'alta',
                ];
            }
        }
        return $out;
    }

    private function tableExists(string $tabela): bool
    {
        static $cache = [];
        if (array_key_exists($tabela, $cache)) {
            return $cache[$tabela];
        }
        try {
            $row = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1",
                [$tabela]
            );
            $cache[$tabela] = !empty($row['ok']);
        } catch (Throwable $e) {
            $cache[$tabela] = false;
        }
        return $cache[$tabela];
    }

    /** @return array<string,bool> */
    private function colunas(string $tabela): array
    {
        $cols = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ?",
                [$tabela]
            ) ?: [];
            foreach ($rows as $r) {
                $cols[(string) $r['COLUMN_NAME']] = true;
            }
        } catch (Throwable $e) {
            $cols = [];
        }
        return $cols;
    }
}
