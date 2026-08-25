<?php

namespace App\Modulos\Matricula\Services;

use Database;

class MatriculaChecklistService
{
    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function schemaReady(): bool
    {
        try {
            return (bool) $this->db->fetch('SHOW TABLES LIKE ?', ['matricula_checklist_itens']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorTipo(string $tipo, bool $somenteAtivos = true): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $tipo = $this->tipoValido($tipo);
        $sql = 'SELECT * FROM matricula_checklist_itens WHERE tipo_processo = ?';
        $params = [$tipo];
        if ($somenteAtivos) {
            $sql .= ' AND ativo = 1';
        }
        $sql .= ' ORDER BY ordem ASC, id ASC';
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarTodos(bool $somenteAtivos = false): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = 'SELECT * FROM matricula_checklist_itens';
        if ($somenteAtivos) {
            $sql .= ' WHERE ativo = 1';
        }
        $sql .= ' ORDER BY tipo_processo, ordem ASC, id ASC';
        return $this->db->fetchAll($sql) ?: [];
    }

    /**
     * @param list<array<string,mixed>> $itens
     */
    public function salvarLote(array $itens): void
    {
        if (!$this->schemaReady()) {
            return;
        }
        foreach ($itens as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $tipo = $this->tipoValido((string) ($row['tipo_processo'] ?? 'nova'));
            $codigo = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($row['codigo'] ?? '')))) ?: null;
            $rotulo = mb_substr(trim((string) ($row['rotulo'] ?? '')), 0, 160);
            if ($codigo === null || $rotulo === '') {
                continue;
            }
            $obrigatorio = !empty($row['obrigatorio']) ? 1 : 0;
            $ativo = !empty($row['ativo']) ? 1 : 0;
            $ordem = max(1, (int) ($row['ordem'] ?? ($i + 1)));

            if ($id > 0) {
                $this->db->update(
                    'UPDATE matricula_checklist_itens
                     SET tipo_processo = ?, codigo = ?, rotulo = ?, obrigatorio = ?, ativo = ?, ordem = ?
                     WHERE id = ?',
                    [$tipo, $codigo, $rotulo, $obrigatorio, $ativo, $ordem, $id]
                );
                continue;
            }
            $existe = $this->db->fetch(
                'SELECT id FROM matricula_checklist_itens WHERE tipo_processo = ? AND codigo = ? LIMIT 1',
                [$tipo, $codigo]
            );
            if ($existe) {
                $this->db->update(
                    'UPDATE matricula_checklist_itens
                     SET rotulo = ?, obrigatorio = ?, ativo = ?, ordem = ?
                     WHERE id = ?',
                    [$rotulo, $obrigatorio, $ativo, $ordem, (int) $existe['id']]
                );
                continue;
            }
            $this->db->insert(
                'INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
                 VALUES (?,?,?,?,?,?)',
                [$tipo, $codigo, $rotulo, $obrigatorio, $ativo, $ordem]
            );
        }
    }

    /**
     * @param list<array<string,mixed>> $documentos
     * @return list<string>
     */
    public function faltandoObrigatorios(string $tipo, array $documentos): array
    {
        $itens = $this->listarPorTipo($tipo, true);
        $anexados = [];
        foreach ($documentos as $doc) {
            $codigo = strtolower(trim((string) ($doc['tipo'] ?? '')));
            if ($codigo !== '') {
                $anexados[$codigo] = true;
            }
        }
        $faltando = [];
        foreach ($itens as $item) {
            if (empty($item['obrigatorio'])) {
                continue;
            }
            $codigo = strtolower((string) ($item['codigo'] ?? ''));
            if ($codigo !== '' && empty($anexados[$codigo])) {
                $faltando[] = (string) ($item['rotulo'] ?? $codigo);
            }
        }
        return $faltando;
    }

    private function tipoValido(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        return in_array($tipo, ['nova', 'rematricula', 'transferencia'], true) ? $tipo : 'nova';
    }
}
