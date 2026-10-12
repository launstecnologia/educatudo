<?php

namespace App\Modulos\ModelosDocumentos\Services;


use App\Core\Database;
/**
 * Guarda cada PDF emitido como uma versão. O download lê o arquivo salvo.
 */
class ArquivoEmissaoDocumentoService
{
    public const TIPO_ARQUIVO = 'documentos_emissoes';

    private Database $db;
    private ModeloDocumentoService $modelos;
    private EmissaoCampoVariavelService $emissao;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->modelos = new ModeloDocumentoService($this->db);
        $this->emissao = new EmissaoCampoVariavelService($this->db);
    }

    public function tabelaPronta(): bool
    {
        try {
            return (bool) $this->db->fetch("SHOW TABLES LIKE 'documentos_emissoes'");
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array{id:int,nome:string,codigo:string,categoria:string,campos:list<array<string,mixed>>}>
     */
    public function catalogo(): array
    {
        if (!$this->modelos->schemaReady()) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT id, nome, codigo, estrutura_json
                 FROM secretaria_modelos_documentos
                 WHERE ativo = 1
                 ORDER BY nome ASC'
            ) ?: [];
        } catch (\Throwable $e) {
            $rows = $this->modelos->listar(true);
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $codigo = (string) ($row['codigo'] ?? '');
            $categoria = ModeloDocumentoService::categoriaDoCodigo($codigo);
            if ($categoria === 'oficial') {
                continue;
            }
            $campos = [];
            if (isset($row['estrutura_json'])) {
                $est = json_decode((string) $row['estrutura_json'], true);
                $campos = ModeloDocumentoService::camposVariaveisValidos(
                    is_array($est) ? ($est['campos_variaveis'] ?? null) : null
                );
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => (string) ($row['nome'] ?? ''),
                'codigo' => $codigo,
                'categoria' => $categoria,
                'campos' => $campos,
            ];
        }
        return $out;
    }

    /**
     * @return array{anos:list<int>,series:list<array{id:int,nome:string}>,turmas:list<array{id:int,nome:string,serie_id:int}>}
     */
    public function opcoesFiltro(): array
    {
        return [
            'anos' => $this->listarAnos(),
            'series' => $this->listarSeries(),
            'turmas' => $this->listarTurmas(),
        ];
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function alunosDaTurma(int $turmaId): array
    {
        if ($turmaId <= 0) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT a.id, a.nome
                 FROM matricula m
                 INNER JOIN alunos a ON a.id = m.aluno_id
                 WHERE m.turma_id = :turma AND m.status IN ('ativa', 'concluido')
                 ORDER BY a.nome ASC
                 LIMIT 200",
                ['turma' => $turmaId]
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = ['id' => $id, 'nome' => (string) ($row['nome'] ?? '')];
        }
        return $out;
    }

    /**
     * @param array{ano:int,serie_id:int,turma_id:int,aluno_id:int,modelo_id:int} $filtro
     * @return list<array<string,mixed>>
     */
    public function listar(array $filtro): array
    {
        if (!$this->tabelaPronta()) {
            return [];
        }
        $ano = (int) ($filtro['ano'] ?? 0);
        $sql = 'SELECT id, modelo_id, codigo, nome, ano_letivo, serie_nome, turma_nome, aluno_nome,
                       versao, total_alunos, emitido_nome, created_at
                FROM documentos_emissoes WHERE ano_letivo = :ano';
        $params = ['ano' => $ano];
        if ((int) ($filtro['serie_id'] ?? 0) > 0) {
            $sql .= ' AND serie_id = :serie';
            $params['serie'] = (int) $filtro['serie_id'];
        }
        if ((int) ($filtro['turma_id'] ?? 0) > 0) {
            $sql .= ' AND turma_id = :turma';
            $params['turma'] = (int) $filtro['turma_id'];
        }
        if ((int) ($filtro['aluno_id'] ?? 0) > 0) {
            $sql .= ' AND aluno_id = :aluno';
            $params['aluno'] = (int) $filtro['aluno_id'];
        }
        if ((int) ($filtro['modelo_id'] ?? 0) > 0) {
            $sql .= ' AND modelo_id = :modelo';
            $params['modelo'] = (int) $filtro['modelo_id'];
        }
        $sql .= ' ORDER BY id DESC LIMIT 80';
        try {
            $rows = $this->db->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param array{ano:int,serie_id:int,turma_id:int,aluno_id:int} $escopo
     * @param array<string,mixed> $valores
     * @param array<string,mixed>|null $config
     * @param array<string,mixed>|null $user
     */
    public function gerar(int $modeloId, array $escopo, array $valores, ?array $config, ?array $user): int
    {
        if (!$this->tabelaPronta()) {
            throw new \RuntimeException('Execute a migration 2026_10_02_documentos_emissoes.sql no painel Master.');
        }
        $ano = (int) ($escopo['ano'] ?? 0);
        if ($ano < 2000) {
            throw new \InvalidArgumentException('Escolha o ano letivo.');
        }
        $ids = $this->idsDoEscopo($escopo);
        $pacote = $this->emissao->montarPdf($modeloId, $ids, $valores, $config, $user);
        $modelo = $pacote['modelo'];
        $serieId = (int) ($escopo['serie_id'] ?? 0);
        $turmaId = (int) ($escopo['turma_id'] ?? 0);
        $alunoId = (int) ($escopo['aluno_id'] ?? 0);
        $versao = $this->proximaVersao((int) ($modelo['id'] ?? 0), $ano, $serieId, $turmaId, $alunoId);
        $chave = $ano . '/m' . (int) ($modelo['id'] ?? 0) . '_v' . $versao . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $tmp = tempnam(sys_get_temp_dir(), 'docemi');
        if ($tmp === false) {
            throw new \RuntimeException('Não foi possível gravar o PDF.');
        }
        try {
            if (file_put_contents($tmp, $pacote['binario']) === false) {
                throw new \RuntimeException('Não foi possível gravar o PDF.');
            }
            $media = new \App\Services\MediaStorageService(is_array($config) ? $config : []);
            if (!$media->put(self::TIPO_ARQUIVO, $chave, $tmp, 'application/pdf')) {
                throw new \RuntimeException('Não foi possível arquivar o PDF.');
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
        $alunoNome = null;
        if ($alunoId > 0 && count($ids) === 1) {
            $row = $this->db->fetch('SELECT nome FROM alunos WHERE id = :id LIMIT 1', ['id' => $alunoId]);
            $alunoNome = is_array($row) ? (string) ($row['nome'] ?? '') : null;
        }
        return (int) $this->db->insert(
            'INSERT INTO documentos_emissoes
                (modelo_id, codigo, nome, ano_letivo, serie_id, serie_nome, turma_id, turma_nome,
                 aluno_id, aluno_nome, campos_json, versao, total_alunos, arquivo_key,
                 emitido_por, emitido_nome, created_at)
             VALUES
                (:modelo_id, :codigo, :nome, :ano, :serie_id, :serie_nome, :turma_id, :turma_nome,
                 :aluno_id, :aluno_nome, :campos, :versao, :total, :arquivo,
                 :emitido_por, :emitido_nome, NOW())',
            [
                'modelo_id' => (int) ($modelo['id'] ?? 0),
                'codigo' => mb_substr((string) ($modelo['codigo'] ?? ''), 0, 80),
                'nome' => mb_substr((string) ($modelo['nome'] ?? 'Documento'), 0, 180),
                'ano' => $ano,
                'serie_id' => $serieId > 0 ? $serieId : null,
                'serie_nome' => $this->nomeSerie($serieId),
                'turma_id' => $turmaId > 0 ? $turmaId : null,
                'turma_nome' => $this->nomeTurma($turmaId),
                'aluno_id' => $alunoId > 0 ? $alunoId : null,
                'aluno_nome' => $alunoNome,
                'campos' => json_encode($valores, JSON_UNESCAPED_UNICODE),
                'versao' => $versao,
                'total' => (int) $pacote['total'],
                'arquivo' => $chave,
                'emitido_por' => isset($user['id']) ? (int) $user['id'] : null,
                'emitido_nome' => isset($user['nome']) ? mb_substr((string) $user['nome'], 0, 255) : null,
            ]
        );
    }

    /**
     * @return array{nome:string,binario:string}|null
     */
    public function baixar(int $id, ?array $config): ?array
    {
        if ($id <= 0 || !$this->tabelaPronta()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT nome, versao, arquivo_key FROM documentos_emissoes WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!is_array($row)) {
            return null;
        }
        $chave = (string) ($row['arquivo_key'] ?? '');
        $media = new \App\Services\MediaStorageService(is_array($config) ? $config : []);
        $binario = $media->getContents(self::TIPO_ARQUIVO, $chave);
        if (!is_string($binario) || $binario === '') {
            return null;
        }
        $nome = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($row['nome'] ?? 'documento')) ?: 'documento';
        return [
            'nome' => $nome . '_v' . (int) ($row['versao'] ?? 1) . '.pdf',
            'binario' => $binario,
        ];
    }

    /**
     * @param array{ano:int,serie_id:int,turma_id:int,aluno_id:int} $escopo
     * @return list<int>
     */
    private function idsDoEscopo(array $escopo): array
    {
        $alunoId = (int) ($escopo['aluno_id'] ?? 0);
        if ($alunoId > 0) {
            $aluno = $this->db->fetch('SELECT id FROM alunos WHERE id = :id LIMIT 1', ['id' => $alunoId]);
            if (!is_array($aluno)) {
                throw new \InvalidArgumentException('Aluno não encontrado.');
            }
            $turmaId = (int) ($escopo['turma_id'] ?? 0);
            if ($turmaId > 0) {
                $daTurma = array_column($this->alunosDaTurma($turmaId), 'id');
                if (!in_array($alunoId, $daTurma, true)) {
                    throw new \InvalidArgumentException('Este aluno não está na turma escolhida.');
                }
            }
            return [$alunoId];
        }
        $turmaId = (int) ($escopo['turma_id'] ?? 0);
        if ($turmaId > 0) {
            return array_column($this->alunosDaTurma($turmaId), 'id');
        }
        $ano = (int) ($escopo['ano'] ?? 0);
        $serieId = (int) ($escopo['serie_id'] ?? 0);
        $sql = "SELECT DISTINCT a.id, a.nome
                FROM matricula m
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN turmas t ON t.id = m.turma_id
                INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                WHERE m.status IN ('ativa', 'concluido') AND al.ano = :ano";
        $params = ['ano' => $ano];
        if ($serieId > 0) {
            $sql .= ' AND t.serie_id = :serie';
            $params['serie'] = $serieId;
        }
        $sql .= ' ORDER BY a.nome ASC LIMIT 121';
        try {
            $rows = $this->db->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            error_log('ArquivoEmissaoDocumento idsDoEscopo: ' . $e->getMessage());
            throw new \RuntimeException('Não foi possível listar os alunos deste ano e desta série. Escolha a turma.');
        }
        $ids = [];
        foreach ($rows as $row) {
            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                $ids[] = (int) $row['id'];
            }
        }
        return $ids;
    }

    private function proximaVersao(int $modeloId, int $ano, int $serieId, int $turmaId, int $alunoId): int
    {
        $sql = 'SELECT COALESCE(MAX(versao), 0) + 1 AS prox
                FROM documentos_emissoes
                WHERE modelo_id = :modelo AND ano_letivo = :ano';
        $params = ['modelo' => $modeloId, 'ano' => $ano];
        if ($serieId > 0) {
            $sql .= ' AND serie_id = :serie';
            $params['serie'] = $serieId;
        } else {
            $sql .= ' AND serie_id IS NULL';
        }
        if ($turmaId > 0) {
            $sql .= ' AND turma_id = :turma';
            $params['turma'] = $turmaId;
        } else {
            $sql .= ' AND turma_id IS NULL';
        }
        if ($alunoId > 0) {
            $sql .= ' AND aluno_id = :aluno';
            $params['aluno'] = $alunoId;
        } else {
            $sql .= ' AND aluno_id IS NULL';
        }
        $row = $this->db->fetch($sql, $params);
        return max(1, (int) (is_array($row) ? ($row['prox'] ?? 1) : 1));
    }

    /**
     * @return list<int>
     */
    private function listarAnos(): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT ano FROM ano_letivo ORDER BY ano DESC LIMIT 12'
            ) ?: [];
        } catch (\Throwable $e) {
            return [(int) date('Y')];
        }
        $anos = [];
        foreach ($rows as $row) {
            if (is_array($row) && (int) ($row['ano'] ?? 0) > 0) {
                $anos[] = (int) $row['ano'];
            }
        }
        return $anos !== [] ? $anos : [(int) date('Y')];
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    private function listarSeries(): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT id, nome FROM serie WHERE ativo = 1 ORDER BY ordem ASC, nome ASC'
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || (int) ($row['id'] ?? 0) <= 0) {
                continue;
            }
            $out[] = ['id' => (int) $row['id'], 'nome' => (string) ($row['nome'] ?? '')];
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string,serie_id:int}>
     */
    private function listarTurmas(): array
    {
        $temSerie = false;
        try {
            $col = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'turmas' AND column_name = 'serie_id' LIMIT 1"
            );
            $temSerie = !empty($col['ok']);
        } catch (\Throwable $e) {
            $temSerie = false;
        }
        $sql = $temSerie
            ? 'SELECT id, nome, serie_id FROM turmas WHERE ativo = 1 ORDER BY nome ASC LIMIT 300'
            : 'SELECT id, nome, 0 AS serie_id FROM turmas WHERE ativo = 1 ORDER BY nome ASC LIMIT 300';
        try {
            $rows = $this->db->fetchAll($sql) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || (int) ($row['id'] ?? 0) <= 0) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'nome' => (string) ($row['nome'] ?? ''),
                'serie_id' => (int) ($row['serie_id'] ?? 0),
            ];
        }
        return $out;
    }

    private function nomeSerie(int $serieId): ?string
    {
        if ($serieId <= 0) {
            return null;
        }
        $row = $this->db->fetch('SELECT nome FROM serie WHERE id = :id LIMIT 1', ['id' => $serieId]);
        $nome = is_array($row) ? trim((string) ($row['nome'] ?? '')) : '';
        return $nome !== '' ? mb_substr($nome, 0, 120) : null;
    }

    private function nomeTurma(int $turmaId): ?string
    {
        if ($turmaId <= 0) {
            return null;
        }
        $row = $this->db->fetch('SELECT nome FROM turmas WHERE id = :id LIMIT 1', ['id' => $turmaId]);
        $nome = is_array($row) ? trim((string) ($row['nome'] ?? '')) : '';
        return $nome !== '' ? mb_substr($nome, 0, 180) : null;
    }
}

if (!class_exists('ArquivoEmissaoDocumentoService', false) && class_exists(__NAMESPACE__ . '\\ArquivoEmissaoDocumentoService', false)) {
    class_alias(__NAMESPACE__ . '\\ArquivoEmissaoDocumentoService', 'ArquivoEmissaoDocumentoService');
}
