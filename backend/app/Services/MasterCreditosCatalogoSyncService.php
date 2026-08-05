<?php
/**
 * Vínculos escola ↔ catálogos Master + sincronização no banco tenant.
 */
namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../Core/CreditosDecimalHelper.php';
require_once __DIR__ . '/../Core/MasterTenantConnection.php';

class MasterCreditosCatalogoSyncService
{
    /**
     * Vincula a tabela de preço marcada como padrão à escola nova e aplica custos no layout.
     *
     * @return array{layout_patch: array<string,string>, errors: list<string>}
     */
    public static function aplicarTabelaPadraoNaEscolaNova(int $escolaId): array
    {
        $out = ['layout_patch' => [], 'errors' => []];
        if ($escolaId <= 0) {
            return $out;
        }
        $db = \Database::getInstance();
        try {
            $padrao = $db->fetch(
                'SELECT id FROM creditos_tabela_custo WHERE padrao = 1 AND ativo = 1 ORDER BY id ASC LIMIT 1'
            );
        } catch (\Throwable $e) {
            return $out;
        }
        if (!$padrao) {
            return $out;
        }
        $tabelaId = (int) ($padrao['id'] ?? 0);
        if ($tabelaId <= 0) {
            return $out;
        }

        $pacIds = [];
        $plIds = [];
        try {
            $pacIds = array_map(
                static fn ($r) => (int) $r['catalogo_pacote_id'],
                $db->fetchAll('SELECT catalogo_pacote_id FROM escolas_creditos_pacotes WHERE escola_id = ?', [$escolaId])
            );
            $plIds = array_map(
                static fn ($r) => (int) $r['catalogo_plano_id'],
                $db->fetchAll('SELECT catalogo_plano_id FROM escolas_creditos_planos WHERE escola_id = ?', [$escolaId])
            );
        } catch (\Throwable $e) {
            // vínculos opcionais
        }

        try {
            self::salvarVinculos($escolaId, $tabelaId, $pacIds, $plIds);
            $out['layout_patch'] = self::construirPatchLayoutPorTabela($tabelaId);
        } catch (\Throwable $e) {
            $out['errors'][] = $e->getMessage();
        }
        return $out;
    }

    /**
     * Garante no máximo uma tabela padrão (quando $tabelaId for marcada).
     */
    public static function definirTabelaPadrao(int $tabelaId, bool $padrao): void
    {
        $db = \Database::getInstance();
        if ($padrao) {
            $db->query('UPDATE creditos_tabela_custo SET padrao = 0 WHERE padrao = 1');
            $db->query('UPDATE creditos_tabela_custo SET padrao = 1, updated_at = NOW() WHERE id = ?', [$tabelaId]);
            return;
        }
        $db->query('UPDATE creditos_tabela_custo SET padrao = 0, updated_at = NOW() WHERE id = ?', [$tabelaId]);
    }

    /**
     * Persiste seleção de vínculos no banco master.
     *
     * @param list<int> $pacoteCatalogoIds
     * @param list<int> $planoCatalogoIds
     */
    public static function salvarVinculos(int $escolaId, ?int $tabelaCustoId, array $pacoteCatalogoIds, array $planoCatalogoIds): void
    {
        $db = \Database::getInstance();
        $tid = ($tabelaCustoId !== null && $tabelaCustoId > 0) ? $tabelaCustoId : null;
        $db->query(
            'INSERT INTO escolas_creditos_vinculo (escola_id, tabela_custo_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE tabela_custo_id = VALUES(tabela_custo_id)',
            [$escolaId, $tid]
        );
        $db->query('DELETE FROM escolas_creditos_pacotes WHERE escola_id = ?', [$escolaId]);
        foreach ($pacoteCatalogoIds as $pid) {
            $pid = (int) $pid;
            if ($pid > 0) {
                $db->insert(
                    'INSERT INTO escolas_creditos_pacotes (escola_id, catalogo_pacote_id) VALUES (?, ?)',
                    [$escolaId, $pid]
                );
            }
        }
        $db->query('DELETE FROM escolas_creditos_planos WHERE escola_id = ?', [$escolaId]);
        foreach ($planoCatalogoIds as $lid) {
            $lid = (int) $lid;
            if ($lid > 0) {
                $db->insert(
                    'INSERT INTO escolas_creditos_planos (escola_id, catalogo_plano_id) VALUES (?, ?)',
                    [$escolaId, $lid]
                );
            }
        }
    }

    /**
     * @return array<string, string> chaves layout (credito_modulo_*, credito_custo_*)
     */
    public static function construirPatchLayoutPorTabela(int $tabelaId): array
    {
        $db = \Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT modulo_key, creditos, cobra FROM creditos_tabela_custo_item WHERE tabela_id = ?',
            [$tabelaId]
        );
        $byKey = [];
        foreach ($rows as $r) {
            $byKey[(string) $r['modulo_key']] = $r;
        }
        $patch = [];
        foreach (\CreditosModuleRegistry::getModuleKeys() as $mk) {
            if (!isset($byKey[$mk])) {
                continue;
            }
            $row = $byKey[$mk];
            $patch['credito_modulo_' . $mk] = !empty($row['cobra']) ? '1' : '0';
            $c = (float) ($row['creditos'] ?? 1);
            if ($c <= 0) {
                $c = 1.0;
            }
            $patch['credito_custo_' . $mk] = (string) round($c, \CreditosDecimalHelper::SCALE);
        }
        return $patch;
    }

    /**
     * @return array{layout_patch: array<string,string>, errors: list<string>}
     */
    public static function sincronizarTenant(int $escolaId): array
    {
        $db = \Database::getInstance();
        $out = ['layout_patch' => [], 'errors' => []];
        $v = $db->fetch('SELECT tabela_custo_id FROM escolas_creditos_vinculo WHERE escola_id = ?', [$escolaId]);
        $tid = $v ? (int) ($v['tabela_custo_id'] ?? 0) : 0;
        if ($tid > 0) {
            $out['layout_patch'] = self::construirPatchLayoutPorTabela($tid);
        }

        $conn = \MasterTenantConnection::getPdoAndEscola($escolaId);
        if ($conn === null) {
            $out['errors'][] = 'Não foi possível conectar ao banco da escola.';
            return $out;
        }
        $pdo = $conn['pdo'];

        $selPac = $db->fetchAll(
            'SELECT catalogo_pacote_id FROM escolas_creditos_pacotes WHERE escola_id = ?',
            [$escolaId]
        );
        $pacIds = array_map(static fn ($r) => (int) $r['catalogo_pacote_id'], $selPac);

        try {
            self::syncPacotesNoTenant($pdo, $db, $pacIds);
        } catch (\Throwable $e) {
            $out['errors'][] = 'Pacotes: ' . $e->getMessage();
        }

        $selPl = $db->fetchAll(
            'SELECT catalogo_plano_id FROM escolas_creditos_planos WHERE escola_id = ?',
            [$escolaId]
        );
        $plIds = array_map(static fn ($r) => (int) $r['catalogo_plano_id'], $selPl);
        try {
            self::syncPlanosNoTenant($pdo, $db, $plIds);
        } catch (\Throwable $e) {
            $out['errors'][] = 'Planos: ' . $e->getMessage();
        }

        return $out;
    }

    /**
     * @param list<int> $selectedCatalogIds
     */
    private static function syncPacotesNoTenant(\PDO $pdo, \Database $db, array $selectedCatalogIds): void
    {
        try {
            $pdo->query('SELECT catalogo_pacote_id FROM pacotes_creditos LIMIT 1');
        } catch (\Throwable $e) {
            return;
        }

        if ($selectedCatalogIds === []) {
            $pdo->exec('UPDATE pacotes_creditos SET ativo = 0, updated_at = NOW() WHERE catalogo_pacote_id IS NOT NULL');
            return;
        }
        $placeholders = implode(',', array_fill(0, count($selectedCatalogIds), '?'));
        $st = $pdo->prepare(
            "UPDATE pacotes_creditos SET ativo = 0, updated_at = NOW() WHERE catalogo_pacote_id IS NOT NULL AND catalogo_pacote_id NOT IN ($placeholders)"
        );
        $st->execute($selectedCatalogIds);

        foreach ($selectedCatalogIds as $cid) {
            $row = $db->fetch(
                'SELECT id, nome, creditos, valor_centavos FROM creditos_pacotes_catalogo WHERE id = ? AND ativo = 1',
                [$cid]
            );
            if (!$row) {
                continue;
            }
            $sql = 'INSERT INTO pacotes_creditos (nome, creditos, valor_centavos, ativo, catalogo_pacote_id) VALUES (?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE nome = VALUES(nome), creditos = VALUES(creditos), valor_centavos = VALUES(valor_centavos), ativo = 1, updated_at = NOW()';
            $pdo->prepare($sql)->execute([
                $row['nome'],
                $row['creditos'],
                (int) $row['valor_centavos'],
                (int) $row['id'],
            ]);
        }
    }

    /**
     * @param list<int> $selectedCatalogIds
     */
    private static function syncPlanosNoTenant(\PDO $pdo, \Database $db, array $selectedCatalogIds): void
    {
        try {
            $pdo->query('SELECT catalogo_plano_id FROM planos_creditos LIMIT 1');
        } catch (\Throwable $e) {
            return;
        }

        if ($selectedCatalogIds === []) {
            $pdo->exec('UPDATE planos_creditos SET ativo = 0 WHERE catalogo_plano_id IS NOT NULL');
            return;
        }
        $placeholders = implode(',', array_fill(0, count($selectedCatalogIds), '?'));
        $st = $pdo->prepare(
            "UPDATE planos_creditos SET ativo = 0 WHERE catalogo_plano_id IS NOT NULL AND catalogo_plano_id NOT IN ($placeholders)"
        );
        $st->execute($selectedCatalogIds);

        foreach ($selectedCatalogIds as $cid) {
            $row = $db->fetch(
                'SELECT id, nome, creditos_mensais, valor_mensal, destino FROM creditos_planos_catalogo WHERE id = ? AND ativo = 1',
                [$cid]
            );
            if (!$row) {
                continue;
            }
            $sql = 'INSERT INTO planos_creditos (nome, creditos_mensais, valor_mensal, destino, ativo, catalogo_plano_id) VALUES (?, ?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE nome = VALUES(nome), creditos_mensais = VALUES(creditos_mensais), valor_mensal = VALUES(valor_mensal), destino = VALUES(destino), ativo = 1';
            $pdo->prepare($sql)->execute([
                $row['nome'],
                (int) $row['creditos_mensais'],
                $row['valor_mensal'],
                $row['destino'],
                (int) $row['id'],
            ]);
        }
    }
}
