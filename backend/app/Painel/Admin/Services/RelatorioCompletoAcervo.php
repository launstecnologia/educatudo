<?php

namespace App\Painel\Admin\Services;

use App\Modulos\ModelosDocumentos\Services\ArquivoEmissaoDocumentoService;
use App\Services\MediaStorageService;

/**
 * Guarda o relatório completo no arquivo de Documentos e impede
 * a mesma combinação de filtros e bancos dentro de uma hora.
 */
class RelatorioCompletoAcervo
{
    public const CODIGO = 'relatorio_completo';

    public function __construct(private $db)
    {
    }

    /**
     * @param array{ano:int,turma_id:int,busca:string,data_inicio:string,data_fim:string,incluir_inativos:bool,bancos:list<string>} $filtros
     * @return array{id:int,quando:string,formato:string}|null
     */
    public function identicoRecente(array $filtros, string $formato): ?array
    {
        if (!$this->tabelaPronta()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id, created_at FROM documentos_emissoes
             WHERE codigo = :codigo AND campos_json = :campos
               AND created_at >= (NOW() - INTERVAL 1 HOUR)
             ORDER BY id DESC
             LIMIT 1',
            [
                'codigo' => self::CODIGO,
                'campos' => $this->impressao($filtros, $formato),
            ]
        );
        if (!is_array($row) || (int) ($row['id'] ?? 0) <= 0) {
            return null;
        }
        $quando = strtotime((string) ($row['created_at'] ?? ''));

        return [
            'id' => (int) $row['id'],
            'quando' => $quando ? date('d/m/Y H:i', $quando) : '',
            'formato' => $formato === 'pdf' ? 'pdf' : 'excel',
        ];
    }

    /**
     * @param array{ano:int,turma_id:int,busca:string,data_inicio:string,data_fim:string,incluir_inativos:bool,bancos:list<string>} $filtros
     * @param array<string, array{titulo?:string}> $catalogo
     * @param array<string,mixed>|null $user
     * @param array<string,mixed> $config
     */
    public function guardar(array $filtros, string $formato, string $binario, int $totalAlunos, array $catalogo, ?array $user, array $config): int
    {
        if (!$this->tabelaPronta()) {
            throw new \RuntimeException('Execute a migration 2026_10_02_documentos_emissoes.sql no painel Master para guardar o relatório em Documentos.');
        }
        if ($binario === '') {
            throw new \RuntimeException('O arquivo gerado está vazio.');
        }
        $formato = $formato === 'pdf' ? 'pdf' : 'excel';
        $ext = $formato === 'pdf' ? 'pdf' : 'xlsx';
        $mime = $formato === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $ano = (int) $filtros['ano'] > 0 ? (int) $filtros['ano'] : (int) date('Y');
        $turmaId = (int) $filtros['turma_id'];
        $versao = $this->proximaVersao($filtros, $formato);
        $chave = $ano . '/relatorio_completo_v' . $versao . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $tmp = tempnam(sys_get_temp_dir(), 'relac');
        if ($tmp === false) {
            throw new \RuntimeException('Não foi possível guardar o relatório em Documentos.');
        }
        try {
            if (file_put_contents($tmp, $binario) === false) {
                throw new \RuntimeException('Não foi possível guardar o relatório em Documentos.');
            }
            $media = new MediaStorageService($config);
            if (!$media->put(ArquivoEmissaoDocumentoService::TIPO_ARQUIVO, $chave, $tmp, $mime)) {
                throw new \RuntimeException('Não foi possível guardar o relatório em Documentos.');
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        $titulos = [];
        foreach ($filtros['bancos'] as $banco) {
            $titulos[] = (string) ($catalogo[$banco]['titulo'] ?? $banco);
        }
        $rotuloBancos = implode(', ', $titulos);
        if ((int) $filtros['ano'] <= 0) {
            $rotuloBancos = 'Todos os anos · ' . $rotuloBancos;
        }
        if (mb_strlen($rotuloBancos) > 120) {
            $rotuloBancos = mb_substr($rotuloBancos, 0, 117) . '...';
        }
        $busca = trim((string) $filtros['busca']);
        $nome = 'Relatório completo (' . ($formato === 'pdf' ? 'PDF' : 'Excel') . ')';

        return (int) $this->db->insert(
            'INSERT INTO documentos_emissoes
                (modelo_id, codigo, nome, ano_letivo, serie_id, serie_nome, turma_id, turma_nome,
                 aluno_id, aluno_nome, campos_json, versao, total_alunos, arquivo_key,
                 emitido_por, emitido_nome, created_at)
             VALUES
                (0, :codigo, :nome, :ano, NULL, :serie_nome, :turma_id, :turma_nome,
                 NULL, :aluno_nome, :campos, :versao, :total, :arquivo,
                 :emitido_por, :emitido_nome, NOW())',
            [
                'codigo' => self::CODIGO,
                'nome' => $nome,
                'ano' => $ano,
                'serie_nome' => $rotuloBancos,
                'turma_id' => $turmaId > 0 ? $turmaId : null,
                'turma_nome' => $this->nomeTurma($turmaId),
                'aluno_nome' => $busca !== '' ? mb_substr($busca, 0, 180) : null,
                'campos' => $this->impressao($filtros, $formato),
                'versao' => $versao,
                'total' => max(0, $totalAlunos),
                'arquivo' => $chave,
                'emitido_por' => isset($user['id']) ? (int) $user['id'] : null,
                'emitido_nome' => isset($user['nome']) ? mb_substr((string) $user['nome'], 0, 255) : null,
            ]
        );
    }

    /**
     * @return array{nome:string,binario:string,mime:string}|null
     */
    public function baixar(int $id, array $config): ?array
    {
        if ($id <= 0 || !$this->tabelaPronta()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT nome, versao, arquivo_key FROM documentos_emissoes
             WHERE id = :id AND codigo = :codigo
             LIMIT 1',
            ['id' => $id, 'codigo' => self::CODIGO]
        );
        if (!is_array($row)) {
            return null;
        }
        $chave = (string) ($row['arquivo_key'] ?? '');
        $media = new MediaStorageService($config);
        $binario = $media->getContents(ArquivoEmissaoDocumentoService::TIPO_ARQUIVO, $chave);
        if (!is_string($binario) || $binario === '') {
            return null;
        }
        $ext = strtolower(pathinfo($chave, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            $ext = 'pdf';
        }
        $nome = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($row['nome'] ?? 'relatorio')) ?: 'relatorio';

        return [
            'nome' => $nome . '_v' . (int) ($row['versao'] ?? 1) . '.' . $ext,
            'binario' => $binario,
            'mime' => $ext === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/pdf',
        ];
    }

    /**
     * @param array{ano:int,turma_id:int,busca:string,data_inicio:string,data_fim:string,incluir_inativos:bool,bancos:list<string>} $filtros
     */
    private function impressao(array $filtros, string $formato): string
    {
        $bancos = array_values($filtros['bancos']);
        sort($bancos);
        $json = json_encode([
            'tipo' => self::CODIGO,
            'formato' => $formato === 'pdf' ? 'pdf' : 'excel',
            'ano' => (int) $filtros['ano'],
            'turma_id' => (int) $filtros['turma_id'],
            'busca' => (string) $filtros['busca'],
            'data_inicio' => (string) $filtros['data_inicio'],
            'data_fim' => (string) $filtros['data_fim'],
            'incluir_inativos' => !empty($filtros['incluir_inativos']) ? 1 : 0,
            'bancos' => $bancos,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }

    /**
     * @param array{ano:int,turma_id:int,busca:string,data_inicio:string,data_fim:string,incluir_inativos:bool,bancos:list<string>} $filtros
     */
    private function proximaVersao(array $filtros, string $formato): int
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(MAX(versao), 0) + 1 AS prox
             FROM documentos_emissoes
             WHERE codigo = :codigo AND campos_json = :campos',
            [
                'codigo' => self::CODIGO,
                'campos' => $this->impressao($filtros, $formato),
            ]
        );

        return max(1, (int) (is_array($row) ? ($row['prox'] ?? 1) : 1));
    }

    private function nomeTurma(int $turmaId): string
    {
        if ($turmaId <= 0) {
            return 'Todas as turmas';
        }
        try {
            $row = $this->db->fetch('SELECT nome FROM turmas WHERE id = :id LIMIT 1', ['id' => $turmaId]);
        } catch (\Throwable $e) {
            return 'Turma ' . $turmaId;
        }
        $nome = is_array($row) ? trim((string) ($row['nome'] ?? '')) : '';

        return $nome !== '' ? mb_substr($nome, 0, 180) : 'Turma ' . $turmaId;
    }

    private function tabelaPronta(): bool
    {
        try {
            return (bool) $this->db->fetch("SHOW TABLES LIKE 'documentos_emissoes'");
        } catch (\Throwable $e) {
            return false;
        }
    }
}
