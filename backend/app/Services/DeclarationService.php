<?php

namespace App\Services;

use Database;

/**
 * EducaTudo - DeclarationService
 * Reúne os dados necessários para emitir declarações oficiais do aluno
 * (matrícula, frequência, comparecimento, transferência) e registra o
 * histórico/numeração das emissões.
 */
class DeclarationService
{
    /** Declarações oficiais. */
    public const TIPOS = ['matricula', 'frequencia', 'comparecimento', 'transferencia'];

    /** Documentos escolares (histórico, ficha). */
    public const TIPOS_DOCUMENTOS = ['historico', 'ficha_matricula'];

    /** Autorizações (saída, retirada, imagem, passeio). */
    public const TIPOS_AUTORIZACOES = ['aut_saida', 'aut_retirada', 'aut_imagem', 'aut_passeio'];

    /** Tipos que devem ser gerados em paisagem (landscape). */
    public const TIPOS_LANDSCAPE = ['historico'];

    public const TIPO_LABELS = [
        'matricula' => 'Declaração de Matrícula',
        'frequencia' => 'Declaração de Frequência',
        'comparecimento' => 'Declaração de Comparecimento',
        'transferencia' => 'Declaração de Transferência',
        'historico' => 'Histórico Escolar',
        'ficha_matricula' => 'Ficha de Matrícula',
        'aut_saida' => 'Autorização de Saída',
        'aut_retirada' => 'Autorização de Retirada por Terceiros',
        'aut_imagem' => 'Autorização de Uso de Imagem',
        'aut_passeio' => 'Autorização de Passeio/Excursão',
    ];

    /** @var Database */
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Lista completa de tipos suportados (declarações + documentos + autorizações).
     *
     * @return list<string>
     */
    public static function todosOsTipos(): array
    {
        return array_merge(self::TIPOS, self::TIPOS_DOCUMENTOS, self::TIPOS_AUTORIZACOES);
    }

    public static function isTipoValido(string $tipo): bool
    {
        return in_array($tipo, self::todosOsTipos(), true);
    }

    public static function isLandscape(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS_LANDSCAPE, true);
    }

    /**
     * Dados do aluno usados no corpo das declarações.
     *
     * @return array<string, mixed>|null
     */
    public function getAluno(int $alunoId): ?array
    {
        // a.* (em vez de listar a.unidade_id explicitamente) evita erro caso a
        // migration de unidades ainda não tenha rodado neste tenant.
        $row = $this->db->fetch(
            "SELECT a.*, t.nome AS turma_nome, t.serie AS turma_serie
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = ? LIMIT 1",
            [$alunoId]
        );
        return $row ?: null;
    }

    /**
     * Dados institucionais da unidade do aluno (com fallback para a matriz).
     *
     * @param array<string, mixed> $aluno
     * @return array<string, mixed>|null
     */
    public function getUnidadeForAluno(array $aluno): ?array
    {
        require_once __DIR__ . '/../Models/Education/SchoolUnit.php';
        $model = new \SchoolUnit();
        if (!$model->tableExists()) {
            return null;
        }
        $unidadeId = (int) ($aluno['unidade_id'] ?? 0);
        if ($unidadeId > 0) {
            $row = $model->findById($unidadeId);
            if ($row) {
                return $row;
            }
        }
        // Fallback: unidade matriz (mais antiga ativa) para sempre haver cabeçalho.
        $ativas = $model->getActive();
        return $ativas[0] ?? null;
    }

    /**
     * Matrícula ativa mais recente do aluno (com ano letivo).
     *
     * @return array<string, mixed>|null
     */
    public function getMatriculaAtiva(int $alunoId): ?array
    {
        if (!$this->tabelaExiste('matricula')) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT m.id, m.turma_id, m.ano_letivo_id, m.data_entrada, m.data_saida, m.status,
                    t.nome AS turma_nome, t.serie AS turma_serie,
                    al.ano AS ano_letivo
             FROM matricula m
             LEFT JOIN turmas t ON t.id = m.turma_id
             LEFT JOIN ano_letivo al ON al.id = m.ano_letivo_id
             WHERE m.aluno_id = ?
             ORDER BY (m.status = 'ativa') DESC, m.data_entrada DESC, m.id DESC
             LIMIT 1",
            [$alunoId]
        );
        return $row ?: null;
    }

    /**
     * Última matrícula encerrada (transferido/concluído) para a declaração de transferência.
     *
     * @return array<string, mixed>|null
     */
    public function getMatriculaEncerrada(int $alunoId): ?array
    {
        if (!$this->tabelaExiste('matricula')) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT m.id, m.turma_id, m.ano_letivo_id, m.data_entrada, m.data_saida, m.status,
                    t.nome AS turma_nome, t.serie AS turma_serie,
                    al.ano AS ano_letivo
             FROM matricula m
             LEFT JOIN turmas t ON t.id = m.turma_id
             LEFT JOIN ano_letivo al ON al.id = m.ano_letivo_id
             WHERE m.aluno_id = ? AND m.status IN ('transferido','concluido')
             ORDER BY m.data_saida DESC, m.id DESC
             LIMIT 1",
            [$alunoId]
        );
        return $row ?: null;
    }

    /**
     * Calcula a frequência do aluno no período a partir do diário de classe.
     *
     * @return array{total_aulas:int, presencas:int, faltas:int, faltas_justificadas:int, percentual:?float, sem_registros:bool}
     */
    public function getFrequencia(int $alunoId, string $inicio, string $fim): array
    {
        $vazio = [
            'total_aulas' => 0,
            'presencas' => 0,
            'faltas' => 0,
            'faltas_justificadas' => 0,
            'percentual' => null,
            'sem_registros' => true,
        ];
        if (!$this->tabelaExiste('diario_frequencias') || !$this->tabelaExiste('diario_aulas')) {
            return $vazio;
        }
        $row = $this->db->fetch(
            "SELECT
                COUNT(*) AS total_aulas,
                SUM(CASE WHEN f.situacao IN ('presente','atraso') THEN 1 ELSE 0 END) AS presencas,
                SUM(CASE WHEN f.situacao = 'falta' THEN 1 ELSE 0 END) AS faltas,
                SUM(CASE WHEN f.situacao = 'falta_justificada' THEN 1 ELSE 0 END) AS faltas_justificadas
             FROM diario_frequencias f
             INNER JOIN diario_aulas a ON a.id = f.diario_aula_id
             WHERE f.aluno_id = ?
               AND a.status = 'finalizada'
               AND a.data_aula BETWEEN ? AND ?",
            [$alunoId, $inicio, $fim]
        );

        $total = (int) ($row['total_aulas'] ?? 0);
        if ($total <= 0) {
            return $vazio;
        }
        $presencas = (int) ($row['presencas'] ?? 0);
        return [
            'total_aulas' => $total,
            'presencas' => $presencas,
            'faltas' => (int) ($row['faltas'] ?? 0),
            'faltas_justificadas' => (int) ($row['faltas_justificadas'] ?? 0),
            'percentual' => round(($presencas / $total) * 100, 1),
            'sem_registros' => false,
        ];
    }

    /**
     * Responsáveis vinculados ao aluno (financeiro primeiro), para ficha e autorizações.
     *
     * @return list<array<string, mixed>>
     */
    public function getResponsaveis(int $alunoId): array
    {
        if (!$this->tabelaExiste('alunos_responsaveis') || !$this->tabelaExiste('responsaveis')) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT r.nome, r.cpf, r.email, r.telefone, ar.tipo_vinculo, ar.is_financeiro
                 FROM alunos_responsaveis ar
                 INNER JOIN responsaveis r ON r.id = ar.responsavel_id
                 WHERE ar.aluno_id = ? AND ar.ativo = 1
                 ORDER BY ar.is_financeiro DESC, r.nome ASC",
                [$alunoId]
            );
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('DeclarationService getResponsaveis: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Boletins gerados do aluno (todos os anos) para montar o Histórico Escolar completo.
     * Mantém um lançamento por regra (o mais recente), como na geração do boletim PDF.
     *
     * @return list<array<string, mixed>>
     */
    public function getHistorico(int $alunoId): array
    {
        require_once __DIR__ . '/../Models/System/BoletimConfig.php';
        try {
            $cfg = new \BoletimConfig();
            $cfg->ensureSchema();
            $todos = $cfg->getGeneratedBoletinsByAluno($alunoId, 'coordenacao', 'boletim');
        } catch (\Throwable $e) {
            error_log('DeclarationService getHistorico: ' . $e->getMessage());
            return [];
        }

        $seen = [];
        $out = [];
        foreach ((array) $todos as $ev) {
            $rid = (int) ($ev['regra_id'] ?? 0);
            $ano = (int) ($ev['ano_letivo'] ?? 0);
            if ($ano <= 0) {
                $ini = (string) ($ev['data_inicio'] ?? '');
                if ($ini !== '' && preg_match('/^(\d{4})-/', $ini, $m)) {
                    $ano = (int) $m[1];
                }
            }
            $key = $rid . '|' . $ano;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $ev['ano_letivo_calc'] = $ano > 0 ? $ano : (int) date('Y');
            $out[] = $ev;
        }

        usort($out, static function ($a, $b) {
            return ((int) ($a['ano_letivo_calc'] ?? 0)) <=> ((int) ($b['ano_letivo_calc'] ?? 0));
        });

        return $out;
    }

    /**
     * Registra a emissão e devolve o número sequencial atribuído no ano.
     *
     * @param array<string, mixed> $meta
     */
    public function registrarEmissao(int $alunoId, ?int $unidadeId, string $tipo, array $meta, ?int $userId, ?string $userNome): int
    {
        if (!$this->tabelaExiste('declaracoes_emitidas')) {
            return 0;
        }
        $ano = (int) date('Y');
        try {
            $row = $this->db->fetch(
                "SELECT COALESCE(MAX(numero), 0) + 1 AS prox FROM declaracoes_emitidas WHERE ano = ?",
                [$ano]
            );
            $numero = (int) ($row['prox'] ?? 1);

            $this->db->insert(
                "INSERT INTO declaracoes_emitidas
                    (aluno_id, unidade_id, tipo, numero, ano, emitido_por, emitido_nome, meta_json, created_at)
                 VALUES (:aluno_id, :unidade_id, :tipo, :numero, :ano, :emitido_por, :emitido_nome, :meta_json, NOW())",
                [
                    'aluno_id' => $alunoId,
                    'unidade_id' => $unidadeId,
                    'tipo' => $tipo,
                    'numero' => $numero,
                    'ano' => $ano,
                    'emitido_por' => $userId,
                    'emitido_nome' => $userNome,
                    'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                ]
            );
            return $numero;
        } catch (\Throwable $e) {
            error_log('DeclarationService registrarEmissao: ' . $e->getMessage());
            return 0;
        }
    }

    private function tabelaExiste(string $tabela): bool
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
        } catch (\Throwable $e) {
            $cache[$tabela] = false;
        }
        return $cache[$tabela];
    }
}
