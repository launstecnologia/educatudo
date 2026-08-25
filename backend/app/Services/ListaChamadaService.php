<?php

namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';

class ListaChamadaService
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance();
    }

    public function tabelaExiste(): bool
    {
        return $this->db->fetch("SHOW TABLES LIKE 'alunos_turma_chamada'") !== false;
    }

    public function resolverAnoLetivoIdParaTurma(int $turmaId): int
    {
        $t = $this->db->fetch('SELECT ano_letivo FROM turmas WHERE id = :id', ['id' => $turmaId]);
        $ano = (int) ($t['ano_letivo'] ?? date('Y'));
        $row = $this->db->fetch(
            'SELECT id FROM ano_letivo WHERE ano = :ano ORDER BY id DESC LIMIT 1',
            ['ano' => $ano]
        );

        return $row ? (int) $row['id'] : 0;
    }

    public function getConfig(int $turmaId, int $anoLetivoId): array
    {
        if (!$this->tabelaExiste()) {
            return $this->configPadrao();
        }
        $row = $this->db->fetch(
            'SELECT criterio_ordem, data_corte FROM turmas_lista_config WHERE turma_id = :t AND ano_letivo_id = :a',
            ['t' => $turmaId, 'a' => $anoLetivoId]
        );

        return [
            'criterio_ordem' => $row['criterio_ordem'] ?? 'alfabetica',
            'data_corte' => $row['data_corte'] ?? null,
        ];
    }

    public function salvarConfig(int $turmaId, int $anoLetivoId, string $criterio, ?string $dataCorte): void
    {
        if (!$this->tabelaExiste()) {
            return;
        }
        $criterios = ['alfabetica', 'meninas_primeiro', 'meninos_primeiro'];
        if (!in_array($criterio, $criterios, true)) {
            $criterio = 'alfabetica';
        }
        $dataCorteVal = ($dataCorte !== null && $dataCorte !== '') ? $dataCorte : null;
        $ex = $this->db->fetch(
            'SELECT turma_id FROM turmas_lista_config WHERE turma_id = :t AND ano_letivo_id = :a',
            ['t' => $turmaId, 'a' => $anoLetivoId]
        );
        if ($ex) {
            $this->db->query(
                'UPDATE turmas_lista_config SET criterio_ordem = :c, data_corte = :d, updated_at = NOW()
                 WHERE turma_id = :t AND ano_letivo_id = :a',
                ['c' => $criterio, 'd' => $dataCorteVal, 't' => $turmaId, 'a' => $anoLetivoId]
            );
        } else {
            $this->db->insert(
                'INSERT INTO turmas_lista_config (turma_id, ano_letivo_id, criterio_ordem, data_corte) VALUES (:t, :a, :c, :d)',
                ['t' => $turmaId, 'a' => $anoLetivoId, 'c' => $criterio, 'd' => $dataCorteVal]
            );
        }
    }

    public function listarPorTurma(int $turmaId, int $anoLetivoId): array
    {
        if (!$this->tabelaExiste()) {
            return [];
        }

        $sexoSql = $this->sqlSexoAluno();

        return $this->db->fetchAll(
            "SELECT c.numero_chamada, c.entrada_tardia, c.marcado_tr, c.data_entrada_turma,
                    a.id AS aluno_id, a.nome, a.ra, {$sexoSql}, a.ativo
             FROM alunos_turma_chamada c
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE c.turma_id = :t AND c.ano_letivo_id = :a
             ORDER BY c.numero_chamada ASC",
            ['t' => $turmaId, 'a' => $anoLetivoId]
        ) ?: [];
    }

    /**
     * Popula a lista de chamada com todos os alunos já vinculados à turma
     * (cadastro principal em alunos.turma_id ou matrícula ativa) que ainda
     * não possuem número de chamada no ano letivo informado.
     *
     * Retorna a quantidade de alunos adicionados.
     */
    public function backfillTurma(int $turmaId, int $anoLetivoId): int
    {
        if (!$this->tabelaExiste() || $turmaId <= 0 || $anoLetivoId <= 0) {
            return 0;
        }

        $temMatricula = false;
        try {
            $temMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'") !== false;
        } catch (\Throwable $e) {
            $temMatricula = false;
        }

        if ($temMatricula) {
            $alunos = $this->db->fetchAll(
                "SELECT DISTINCT a.id AS aluno_id, m.data_entrada AS data_entrada
                 FROM alunos a
                 LEFT JOIN matricula m ON m.aluno_id = a.id
                    AND m.turma_id = :tid_mat
                    AND m.status = 'ativa'
                    AND m.data_saida IS NULL
                 WHERE a.turma_id = :tid_where OR m.id IS NOT NULL",
                ['tid_mat' => $turmaId, 'tid_where' => $turmaId]
            ) ?: [];
        } else {
            $alunos = $this->db->fetchAll(
                "SELECT a.id AS aluno_id, NULL AS data_entrada FROM alunos a WHERE a.turma_id = :tid",
                ['tid' => $turmaId]
            ) ?: [];
        }

        $adicionados = 0;
        foreach ($alunos as $row) {
            $alunoId = (int) ($row['aluno_id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            $existe = $this->db->fetch(
                'SELECT id FROM alunos_turma_chamada WHERE aluno_id = :al AND turma_id = :t AND ano_letivo_id = :a',
                ['al' => $alunoId, 't' => $turmaId, 'a' => $anoLetivoId]
            );
            if ($existe) {
                continue;
            }
            $dataEntrada = !empty($row['data_entrada']) ? (string) $row['data_entrada'] : null;
            $this->atribuirProximoNumero($alunoId, $turmaId, $anoLetivoId, $dataEntrada);
            $adicionados++;
        }

        return $adicionados;
    }

    public function atribuirProximoNumero(int $alunoId, int $turmaId, int $anoLetivoId, ?string $dataEntrada = null): void
    {
        if (!$this->tabelaExiste() || $alunoId <= 0 || $turmaId <= 0 || $anoLetivoId <= 0) {
            return;
        }

        $dataEntrada = $dataEntrada ?: date('Y-m-d');
        $config = $this->getConfig($turmaId, $anoLetivoId);
        $entradaTardia = 0;
        if (!empty($config['data_corte']) && $dataEntrada > $config['data_corte']) {
            $entradaTardia = 1;
        }

        $ex = $this->db->fetch(
            'SELECT id FROM alunos_turma_chamada WHERE aluno_id = :al AND turma_id = :t AND ano_letivo_id = :a',
            ['al' => $alunoId, 't' => $turmaId, 'a' => $anoLetivoId]
        );
        if ($ex) {
            return;
        }

        if ($entradaTardia) {
            $max = $this->db->fetch(
                'SELECT COALESCE(MAX(numero_chamada), 0) AS mx FROM alunos_turma_chamada WHERE turma_id = :t AND ano_letivo_id = :a',
                ['t' => $turmaId, 'a' => $anoLetivoId]
            );
            $numero = (int) ($max['mx'] ?? 0) + 1;
        } else {
            $numero = $this->proximoNumeroNoBlocoPrincipal($turmaId, $anoLetivoId, $config);
        }

        $this->db->insert(
            'INSERT INTO alunos_turma_chamada (aluno_id, turma_id, ano_letivo_id, numero_chamada, entrada_tardia, data_entrada_turma)
             VALUES (:al, :t, :a, :n, :et, :de)',
            [
                'al' => $alunoId,
                't' => $turmaId,
                'a' => $anoLetivoId,
                'n' => $numero,
                'et' => $entradaTardia,
                'de' => $dataEntrada,
            ]
        );
    }

    public function marcarTR(int $alunoId, int $turmaId, int $anoLetivoId): void
    {
        if (!$this->tabelaExiste()) {
            return;
        }
        $this->db->query(
            'UPDATE alunos_turma_chamada SET marcado_tr = 1 WHERE aluno_id = :al AND turma_id = :t AND ano_letivo_id = :a',
            ['al' => $alunoId, 't' => $turmaId, 'a' => $anoLetivoId]
        );
    }

    public function moverRemanejamento(int $alunoId, int $turmaOrigemId, int $turmaDestinoId, ?int $anoOrigemId = null, ?int $anoDestinoId = null): void
    {
        if (!$this->tabelaExiste() || $alunoId <= 0 || $turmaOrigemId <= 0 || $turmaDestinoId <= 0) {
            return;
        }

        $anoOrigemId = $anoOrigemId ?: $this->resolverAnoLetivoIdParaTurma($turmaOrigemId);
        $anoDestinoId = $anoDestinoId ?: $this->resolverAnoLetivoIdParaTurma($turmaDestinoId);
        if ($anoOrigemId <= 0 || $anoDestinoId <= 0) {
            return;
        }

        $this->db->query(
            'DELETE FROM alunos_turma_chamada WHERE aluno_id = :al AND turma_id = :t AND ano_letivo_id = :a',
            ['al' => $alunoId, 't' => $turmaDestinoId, 'a' => $anoDestinoId]
        );

        $this->atribuirProximoNumero($alunoId, $turmaDestinoId, $anoDestinoId);
    }

    public function recalcularOrdem(int $turmaId, int $anoLetivoId): void
    {
        if (!$this->tabelaExiste()) {
            return;
        }

        $config = $this->getConfig($turmaId, $anoLetivoId);
        $dataCorte = $config['data_corte'] ?? null;

        $sexoSql = $this->sqlSexoAluno();
        $alunos = $this->db->fetchAll(
            "SELECT c.id AS chamada_id, c.aluno_id, c.data_entrada_turma, c.marcado_tr, a.nome, {$sexoSql}
             FROM alunos_turma_chamada c
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE c.turma_id = :t AND c.ano_letivo_id = :a",
            ['t' => $turmaId, 'a' => $anoLetivoId]
        ) ?: [];

        if (empty($alunos)) {
            return;
        }

        $principal = [];
        $tardios = [];
        foreach ($alunos as $row) {
            $entradaTardia = $dataCorte && ($row['data_entrada_turma'] ?? '') > $dataCorte;
            $row['entrada_tardia_calc'] = $entradaTardia ? 1 : 0;
            if ($entradaTardia) {
                $tardios[] = $row;
            } else {
                $principal[] = $row;
            }
        }

        $principal = $this->ordenarBloco($principal, $config['criterio_ordem']);
        usort($tardios, static function ($a, $b) {
            return strcmp((string) ($a['data_entrada_turma'] ?? ''), (string) ($b['data_entrada_turma'] ?? ''));
        });

        $ordenados = array_merge($principal, $tardios);
        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();
        try {
            // A coluna numero_chamada tem UNIQUE (turma_id, ano_letivo_id, numero_chamada).
            // Renumerar direto causaria colisão intermediária, então usamos duas fases:
            // 1) move todos para uma faixa temporária alta; 2) aplica a numeração final.
            $maxRow = $this->db->fetch(
                'SELECT COALESCE(MAX(numero_chamada), 0) AS mx FROM alunos_turma_chamada
                 WHERE turma_id = :t AND ano_letivo_id = :a',
                ['t' => $turmaId, 'a' => $anoLetivoId]
            );
            $temp = (int) ($maxRow['mx'] ?? 0) + 1000;
            foreach ($ordenados as $row) {
                $this->db->query(
                    'UPDATE alunos_turma_chamada SET numero_chamada = :n WHERE id = :id',
                    ['n' => $temp, 'id' => $row['chamada_id']]
                );
                $temp++;
            }

            $numero = 1;
            foreach ($ordenados as $row) {
                $this->db->query(
                    'UPDATE alunos_turma_chamada SET numero_chamada = :n, entrada_tardia = :et WHERE id = :id',
                    [
                        'n' => $numero,
                        'et' => (int) ($row['entrada_tardia_calc'] ?? 0),
                        'id' => $row['chamada_id'],
                    ]
                );
                $numero++;
            }
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function configPadrao(): array
    {
        return ['criterio_ordem' => 'alfabetica', 'data_corte' => null];
    }

    private function proximoNumeroNoBlocoPrincipal(int $turmaId, int $anoLetivoId, array $config): int
    {
        $dataCorte = $config['data_corte'] ?? null;
        if ($dataCorte) {
            $maxPrincipal = $this->db->fetch(
                'SELECT COALESCE(MAX(numero_chamada), 0) AS mx FROM alunos_turma_chamada
                 WHERE turma_id = :t AND ano_letivo_id = :a AND entrada_tardia = 0',
                ['t' => $turmaId, 'a' => $anoLetivoId]
            );
            $maxTardio = $this->db->fetch(
                'SELECT COALESCE(MAX(numero_chamada), 0) AS mx FROM alunos_turma_chamada
                 WHERE turma_id = :t AND ano_letivo_id = :a AND entrada_tardia = 1',
                ['t' => $turmaId, 'a' => $anoLetivoId]
            );
            $mxPrincipal = (int) ($maxPrincipal['mx'] ?? 0);
            $mxTardio = (int) ($maxTardio['mx'] ?? 0);
            if ($mxTardio > 0 && $mxTardio > $mxPrincipal) {
                return $mxTardio;
            }

            return $mxPrincipal + 1;
        }

        $max = $this->db->fetch(
            'SELECT COALESCE(MAX(numero_chamada), 0) AS mx FROM alunos_turma_chamada WHERE turma_id = :t AND ano_letivo_id = :a',
            ['t' => $turmaId, 'a' => $anoLetivoId]
        );

        return (int) ($max['mx'] ?? 0) + 1;
    }

    private function ordenarBloco(array $rows, string $criterio): array
    {
        usort($rows, function ($a, $b) use ($criterio) {
            if ($criterio === 'meninas_primeiro') {
                $pa = $this->pesoSexo($a['sexo'] ?? null, true);
                $pb = $this->pesoSexo($b['sexo'] ?? null, true);
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }
            } elseif ($criterio === 'meninos_primeiro') {
                $pa = $this->pesoSexo($a['sexo'] ?? null, false);
                $pb = $this->pesoSexo($b['sexo'] ?? null, false);
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }
            }

            return strcasecmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
        });

        return $rows;
    }

    private function pesoSexo(?string $sexo, bool $meninasPrimeiro): int
    {
        $sexo = strtoupper((string) $sexo);
        if ($meninasPrimeiro) {
            if ($sexo === 'F') {
                return 0;
            }
            if ($sexo === 'M') {
                return 1;
            }

            return 2;
        }
        if ($sexo === 'M') {
            return 0;
        }
        if ($sexo === 'F') {
            return 1;
        }

        return 2;
    }

    /** @var list<string>|null */
    private $alunoColumnsCache = null;

    /**
     * @return array<string, array{label: string, grupo: string}>
     */
    public function getCamposExportacaoDisponiveis(): array
    {
        $campos = [
            'numero_chamada' => ['label' => 'Nº chamada', 'grupo' => 'lista'],
            'nome' => ['label' => 'Nome', 'grupo' => 'aluno'],
            'ra' => ['label' => 'RA', 'grupo' => 'aluno'],
            'codigo_aluno' => ['label' => 'Código do aluno', 'grupo' => 'aluno'],
            'nickname' => ['label' => 'Nickname (login)', 'grupo' => 'aluno'],
            'email' => ['label' => 'E-mail', 'grupo' => 'aluno'],
            'cpf' => ['label' => 'CPF', 'grupo' => 'aluno'],
            'rg' => ['label' => 'RG', 'grupo' => 'aluno'],
            'data_nasc' => ['label' => 'Data de nascimento', 'grupo' => 'aluno'],
            'sexo' => ['label' => 'Sexo', 'grupo' => 'aluno'],
            'telefone' => ['label' => 'Telefone', 'grupo' => 'aluno'],
            'celular' => ['label' => 'Celular', 'grupo' => 'aluno'],
            'serie' => ['label' => 'Série', 'grupo' => 'aluno'],
            'logradouro' => ['label' => 'Logradouro', 'grupo' => 'endereco'],
            'numero' => ['label' => 'Número', 'grupo' => 'endereco'],
            'complemento' => ['label' => 'Complemento', 'grupo' => 'endereco'],
            'bairro' => ['label' => 'Bairro', 'grupo' => 'endereco'],
            'cidade' => ['label' => 'Cidade', 'grupo' => 'endereco'],
            'uf' => ['label' => 'UF', 'grupo' => 'endereco'],
            'cep' => ['label' => 'CEP', 'grupo' => 'endereco'],
            'data_entrada_turma' => ['label' => 'Data de entrada na turma', 'grupo' => 'lista'],
            'observacao_lista' => ['label' => 'Observação (lista)', 'grupo' => 'lista'],
        ];

        $out = [];
        foreach ($campos as $key => $meta) {
            if (in_array($key, ['numero_chamada', 'data_entrada_turma', 'observacao_lista'], true)) {
                $out[$key] = $meta;
                continue;
            }
            if ($this->alunoTemColuna($key)) {
                $out[$key] = $meta;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorTurmaCompleto(int $turmaId, int $anoLetivoId): array
    {
        if (!$this->tabelaExiste()) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT c.numero_chamada, c.entrada_tardia, c.marcado_tr, c.data_entrada_turma,
                    a.*
             FROM alunos_turma_chamada c
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE c.turma_id = :t AND c.ano_letivo_id = :a
             ORDER BY c.numero_chamada ASC",
            ['t' => $turmaId, 'a' => $anoLetivoId]
        ) ?: [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{formato: string, campos: list<string>, assinatura: bool, logo: bool, orientacao: string}
     */
    public function parseOpcoesExportacao(array $input): array
    {
        $formato = strtolower(trim((string) ($input['formato'] ?? 'pdf')));
        if (!in_array($formato, ['pdf', 'excel'], true)) {
            $formato = 'pdf';
        }

        $disponiveis = array_keys($this->getCamposExportacaoDisponiveis());
        $camposRaw = $input['campos'] ?? $input['campos[]'] ?? [];
        if (!is_array($camposRaw)) {
            $camposRaw = [$camposRaw];
        }
        $campos = [];
        foreach ($camposRaw as $campo) {
            $campo = trim((string) $campo);
            if ($campo !== '' && in_array($campo, $disponiveis, true)) {
                $campos[] = $campo;
            }
        }
        if ($campos === []) {
            $campos = ['numero_chamada', 'nome', 'ra'];
        }

        $assinatura = !empty($input['assinatura']) && (string) $input['assinatura'] !== '0';

        $orientacao = strtolower(trim((string) ($input['orientacao'] ?? 'vertical')));
        if (!in_array($orientacao, ['vertical', 'horizontal'], true)) {
            $orientacao = 'vertical';
        }

        return [
            'formato' => $formato,
            'campos' => $campos,
            'assinatura' => $assinatura,
            'logo' => !empty($input['logo']) && (string) $input['logo'] !== '0',
            'orientacao' => $orientacao,
        ];
    }

    /**
     * @param list<string> $camposSelecionados
     * @return list<string>
     */
    public function resolverOrdemColunasExportacao(array $camposSelecionados, bool $assinatura): array
    {
        $ordem = [];
        $defs = array_keys($this->getCamposExportacaoDisponiveis());
        foreach ($defs as $key) {
            if (in_array($key, $camposSelecionados, true)) {
                $ordem[] = $key;
            }
        }
        if ($assinatura) {
            $ordem[] = 'assinatura';
        }

        return $ordem;
    }

    /**
     * @param list<array<string, mixed>> $alunos
     * @return array{total: int, masculino: int, feminino: int}
     */
    public function calcularTotaisListagem(array $alunos): array
    {
        $masculino = 0;
        $feminino = 0;
        foreach ($alunos as $aluno) {
            $sexo = strtoupper(trim((string) ($aluno['sexo'] ?? '')));
            if ($sexo === 'M') {
                $masculino++;
            } elseif ($sexo === 'F') {
                $feminino++;
            }
        }

        return [
            'total' => count($alunos),
            'masculino' => $masculino,
            'feminino' => $feminino,
        ];
    }

    /**
     * @param list<string> $colunas
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function montarDadosExportacao(array $alunos, array $colunas): array
    {
        $labels = $this->getCamposExportacaoDisponiveis();
        $headers = [];
        foreach ($colunas as $col) {
            if ($col === 'assinatura') {
                $headers[] = 'Assinatura';
            } else {
                $headers[] = $labels[$col]['label'] ?? $col;
            }
        }

        $rows = [];
        foreach ($alunos as $aluno) {
            $row = [];
            foreach ($colunas as $col) {
                $row[] = $col === 'assinatura' ? '' : $this->formatarValorExportacao($col, $aluno);
            }
            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    public function resolverLogoUrlExportacao(bool $incluir): string
    {
        if (!$incluir) {
            return '';
        }
        if (!class_exists('LayoutHelper')) {
            require_once __DIR__ . '/../Core/LayoutHelper.php';
        }
        $logoUrl = (string) (\LayoutHelper::getLogoHorizontalUrl() ?: \LayoutHelper::getLogoUrl() ?: '');
        if ($logoUrl === '') {
            return '';
        }
        if (strpos($logoUrl, 'http') !== 0 && defined('URL')) {
            $logoUrl = rtrim((string) URL, '/') . '/' . ltrim($logoUrl, '/');
        }

        return $logoUrl;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function formatarValorExportacao(string $campo, array $row): string
    {
        switch ($campo) {
            case 'numero_chamada':
                return (string) (int) ($row['numero_chamada'] ?? 0);
            case 'nome':
                $nome = trim((string) ($row['nome'] ?? ''));
                if (!empty($row['marcado_tr'])) {
                    $nome .= ' TR';
                }
                return $nome;
            case 'sexo':
                $sexo = strtoupper((string) ($row['sexo'] ?? ''));
                if ($sexo === 'F') {
                    return 'Feminino';
                }
                if ($sexo === 'M') {
                    return 'Masculino';
                }
                if ($sexo === 'N') {
                    return 'Neutro / outro';
                }
                return '';
            case 'data_entrada_turma':
            case 'data_nasc':
                return $this->formatarDataBr($row[$campo] ?? null);
            case 'observacao_lista':
                $parts = [];
                if (!empty($row['entrada_tardia'])) {
                    $parts[] = 'Entrada tardia';
                }
                if (!empty($row['marcado_tr'])) {
                    $parts[] = 'TR';
                }
                return implode('; ', $parts);
            case 'cpf':
                if (!class_exists('StudentFormHelper')) {
                    require_once __DIR__ . '/../Helpers/StudentFormHelper.php';
                }
                return \StudentFormHelper::formatCpfDisplay($row['cpf'] ?? '');
            case 'rg':
                if (!class_exists('StudentFormHelper')) {
                    require_once __DIR__ . '/../Helpers/StudentFormHelper.php';
                }
                return \StudentFormHelper::formatRgDisplay($row['rg'] ?? '');
            case 'cep':
                if (!class_exists('StudentFormHelper')) {
                    require_once __DIR__ . '/../Helpers/StudentFormHelper.php';
                }
                return \StudentFormHelper::formatCepDisplay($row['cep'] ?? '');
            case 'telefone':
            case 'celular':
                if (!class_exists('StudentFormHelper')) {
                    require_once __DIR__ . '/../Helpers/StudentFormHelper.php';
                }
                return \StudentFormHelper::formatTelefoneDisplay($row[$campo] ?? '');
            default:
                return trim((string) ($row[$campo] ?? ''));
        }
    }

    private function formatarDataBr($value): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return '';
        }
        $ts = strtotime((string) $value);
        if ($ts === false) {
            return trim((string) $value);
        }

        return date('d/m/Y', $ts);
    }

    /** Fragmento SQL seguro: só referencia a.sexo se a coluna existir. */
    private function sqlSexoAluno(): string
    {
        return $this->alunoTemColuna('sexo') ? 'a.sexo' : 'NULL AS sexo';
    }

    private function alunoTemColuna(string $coluna): bool
    {
        if ($this->alunoColumnsCache === null) {
            $this->alunoColumnsCache = [];
            try {
                $rows = $this->db->fetchAll('SHOW COLUMNS FROM alunos') ?: [];
                foreach ($rows as $row) {
                    if (!empty($row['Field'])) {
                        $this->alunoColumnsCache[] = (string) $row['Field'];
                    }
                }
            } catch (\Throwable $e) {
                $this->alunoColumnsCache = [];
            }
        }

        return in_array($coluna, $this->alunoColumnsCache, true);
    }
}
