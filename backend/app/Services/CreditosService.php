<?php
/**
 * EducaTudo - Serviço de créditos e carteira (tenant).
 * Usa config_layout (LayoutHelper) para creditos_habilitado, credito_modulo_*, credito_custo_*.
 */

namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/LayoutHelper.php';
require_once __DIR__ . '/../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../Core/CreditosDecimalHelper.php';

if (!class_exists('App\\Services\\CreditosService', false)) {
class CreditosService
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance();
    }

    public function isCreditosHabilitado(): bool
    {
        $v = \LayoutHelper::get('creditos_habilitado', '0');
        return $v === '1' || $v === 1 || $v === true;
    }

    /** Consumo do aluno/professor debita a carteira institucional da escola. */
    public function isModoPoolEscola(): bool
    {
        $v = \LayoutHelper::get('creditos_modo_pool_escola', '0');
        return $v === '1' || $v === 1 || $v === true;
    }

    public function moduloCobraCredito(string $moduloKey): bool
    {
        if (!\CreditosModuleRegistry::isValid($moduloKey)) {
            return false;
        }
        $v = \LayoutHelper::get('credito_modulo_' . $moduloKey, '0');
        return $v === '1' || $v === 1 || $v === true;
    }

    /**
     * Resolve quem paga o débito desta ação.
     * @return array{0:string,1:int} [userType, userId]
     */
    private function resolverPagador(string $userType, int $userId, string $moduloKey): array
    {
        if (\CreditosModuleRegistry::getPagador($moduloKey) === 'escola') {
            return ['escola', \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID];
        }
        if ($this->isModoPoolEscola() && ($userType === 'aluno' || $userType === 'professor')) {
            return ['escola', \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID];
        }
        return [$userType, $userId];
    }

    public function getCustoModulo(string $moduloKey): float
    {
        $c = \LayoutHelper::get('credito_custo_' . $moduloKey, '');
        if ($c !== '' && is_numeric($c)) {
            $n = (float) $c;
            return $n >= 0 ? round($n, \CreditosDecimalHelper::SCALE) : 1.0;
        }
        return 1.0;
    }

    public function getSaldo(string $userType, int $userId): float
    {
        $wallet = $this->getWalletSaldos($userType, $userId);
        return $wallet['saldo_total'];
    }

    /**
     * @return array{saldo_total:float,saldo_escola:float,saldo_comprado:float}
     */
    public function getWalletSaldos(string $userType, int $userId): array
    {
        $this->assertUserType($userType, true);
        $this->ensureWalletSchema();

        $row = $this->db->fetch(
            "SELECT saldo, saldo_escola, saldo_comprado
             FROM carteira_usuarios
             WHERE user_type = :ut AND user_id = :uid",
            ['ut' => $userType, 'uid' => $userId]
        );

        if ($row === null) {
            $this->db->insert(
                "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado)
                 VALUES (:ut, :uid, 0, 0, 0)",
                ['ut' => $userType, 'uid' => $userId]
            );
            return [
                'saldo_total' => 0.0,
                'saldo_escola' => 0.0,
                'saldo_comprado' => 0.0,
            ];
        }

        $saldoEscola = \CreditosDecimalHelper::fromScalar($row['saldo_escola'] ?? 0, 0.0);
        $saldoComprado = \CreditosDecimalHelper::fromScalar($row['saldo_comprado'] ?? 0, 0.0);
        $saldoTotal = round($saldoEscola + $saldoComprado, \CreditosDecimalHelper::SCALE);
        $saldoAtual = \CreditosDecimalHelper::fromScalar($row['saldo'] ?? 0, 0.0);

        if (abs($saldoAtual - $saldoTotal) > 0.00009) {
            $this->syncWalletSaldos($userType, $userId, $saldoEscola, $saldoComprado);
        }

        return [
            'saldo_total' => $saldoTotal,
            'saldo_escola' => $saldoEscola,
            'saldo_comprado' => $saldoComprado,
        ];
    }

    public function podeConsumir(string $userType, int $userId, string $moduloKey): bool
    {
        if (!\CreditosModuleRegistry::isValid($moduloKey)) {
            return true;
        }
        // TudiCoins off: ações de IA não rodam.
        if (!$this->isCreditosHabilitado()) {
            return false;
        }
        if (!$this->moduloCobraCredito($moduloKey)) {
            return true; // sistema on, mas este item não cobra → libera sem debitar
        }
        $custo = $this->getCustoModulo($moduloKey);
        if ($custo <= 0) {
            return true;
        }
        [$payType, $payId] = $this->resolverPagador($userType, $userId, $moduloKey);
        return round($this->getSaldo($payType, $payId), \CreditosDecimalHelper::SCALE) + 1e-9 >= round($custo, \CreditosDecimalHelper::SCALE);
    }

    public function consumir(string $userType, int $userId, string $moduloKey, $referenciaId = null): void
    {
        $this->assertUserType($userType, true);
        if (!\CreditosModuleRegistry::isValid($moduloKey)) {
            return;
        }
        if (!$this->isCreditosHabilitado()) {
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        if (!$this->moduloCobraCredito($moduloKey)) {
            return;
        }
        $custo = $this->getCustoModulo($moduloKey);
        if ($custo <= 0) {
            return;
        }

        [$payType, $payId] = $this->resolverPagador($userType, $userId, $moduloKey);
        $this->ensureWalletSchema();

        $ownTx = !$this->db->inTransaction();
        if ($ownTx) {
            $this->db->beginTransaction();
        }
        try {
            $row = $this->db->fetch(
                "SELECT saldo, saldo_escola, saldo_comprado
                 FROM carteira_usuarios
                 WHERE user_type = :ut AND user_id = :uid
                 FOR UPDATE",
                ['ut' => $payType, 'uid' => $payId]
            );
            if ($row === null) {
                $this->db->insert(
                    "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado)
                     VALUES (:ut, :uid, 0, 0, 0)",
                    ['ut' => $payType, 'uid' => $payId]
                );
                $row = [
                    'saldo' => 0,
                    'saldo_escola' => 0,
                    'saldo_comprado' => 0,
                ];
            }

            $saldoEscola = \CreditosDecimalHelper::fromScalar($row['saldo_escola'] ?? 0, 0.0);
            $saldoComprado = \CreditosDecimalHelper::fromScalar($row['saldo_comprado'] ?? 0, 0.0);
            $saldoTotal = round($saldoEscola + $saldoComprado, \CreditosDecimalHelper::SCALE);
            if (round($saldoTotal, \CreditosDecimalHelper::SCALE) + 1e-9 < round($custo, \CreditosDecimalHelper::SCALE)) {
                throw new \Exception('TudiCoins insuficientes. Compre mais ou aguarde a recarga mensal.');
            }

            $consumirEscola = min($saldoEscola, $custo);
            $restante = round($custo - $consumirEscola, \CreditosDecimalHelper::SCALE);
            $consumirComprado = $restante > 0 ? min($saldoComprado, $restante) : 0.0;

            $novoSaldoEscola = round($saldoEscola - $consumirEscola, \CreditosDecimalHelper::SCALE);
            $novoSaldoComprado = round($saldoComprado - $consumirComprado, \CreditosDecimalHelper::SCALE);
            $this->syncWalletSaldos($payType, $payId, $novoSaldoEscola, $novoSaldoComprado);

            $origem = 'misto';
            if ($consumirComprado <= 0.00009) {
                $origem = 'escola';
            } elseif ($consumirEscola <= 0.00009) {
                $origem = 'comprado';
            }

            $ref = $referenciaId !== null ? (string) $referenciaId : null;
            $this->db->insert(
                "INSERT INTO carteira_movimentacoes (user_type, user_id, tipo, saldo_origem, valor, modulo_key, referencia_id)
                 VALUES (:ut, :uid, 'consumo', :origem, :valor, :mk, :ref)",
                [
                    'ut' => $payType,
                    'uid' => $payId,
                    'origem' => $origem,
                    'valor' => -$custo,
                    'mk' => $moduloKey,
                    'ref' => $ref,
                ]
            );

            if ($ownTx) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /** Atalho: debita sempre a carteira institucional da escola. */
    public function consumirEscola(string $moduloKey, $referenciaId = null): void
    {
        $this->consumir('escola', \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, $moduloKey, $referenciaId);
    }

    public function adicionarCreditosEscola(float $valor, string $tipo, $referenciaId = null): void
    {
        $this->adicionarCreditos('escola', \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID, $valor, $tipo, null, $referenciaId);
    }

    public function consumirPrimeiroModuloDisponivel(string $userType, int $userId, array $moduloKeys, $referenciaId): ?string
    {
        $this->assertUserType($userType, true);
        if (!$this->isCreditosHabilitado()) {
            throw new \Exception('TudiCoins desabilitado para esta escola. Ações com IA não estão disponíveis.');
        }
        foreach ($moduloKeys as $mk) {
            if (!\CreditosModuleRegistry::isValid($mk)) {
                continue;
            }
            if (!$this->moduloCobraCredito($mk)) {
                continue;
            }
            $custo = $this->getCustoModulo($mk);
            if ($custo <= 0) {
                continue;
            }
            $this->consumir($userType, $userId, $mk, $referenciaId);
            return $mk;
        }
        return null;
    }

    public function estornar(int $movimentacaoId): void
    {
        $mov = $this->db->fetch(
            "SELECT id, user_type, user_id, valor, modulo_key, referencia_id, saldo_origem
             FROM carteira_movimentacoes
             WHERE id = :id AND tipo = 'consumo' AND valor < 0",
            ['id' => $movimentacaoId]
        );
        if (!$mov) {
            throw new \Exception('Movimentação de consumo não encontrada para estorno.');
        }

        $valorEstorno = round(abs((float) $mov['valor']), \CreditosDecimalHelper::SCALE);
        $wallet = $this->getWalletSaldos($mov['user_type'], (int) $mov['user_id']);
        $saldoEscola = $wallet['saldo_escola'];
        $saldoComprado = $wallet['saldo_comprado'];
        $origem = (string) ($mov['saldo_origem'] ?? 'misto');

        if ($origem === 'comprado') {
            $saldoComprado = round($saldoComprado + $valorEstorno, \CreditosDecimalHelper::SCALE);
        } else {
            $saldoEscola = round($saldoEscola + $valorEstorno, \CreditosDecimalHelper::SCALE);
        }

        $this->syncWalletSaldos($mov['user_type'], (int) $mov['user_id'], $saldoEscola, $saldoComprado);
        $this->db->insert(
            "INSERT INTO carteira_movimentacoes (user_type, user_id, tipo, saldo_origem, valor, modulo_key, referencia_id)
             VALUES (:ut, :uid, 'estorno', :origem, :valor, :mk, :ref)",
            [
                'ut' => $mov['user_type'],
                'uid' => $mov['user_id'],
                'origem' => $origem,
                'valor' => $valorEstorno,
                'mk' => $mov['modulo_key'],
                'ref' => $mov['referencia_id'],
            ]
        );
    }

    public function estornarPorReferencia(string $moduloKey, $referenciaId): void
    {
        $ref = (string) $referenciaId;
        $mov = $this->db->fetch(
            "SELECT id FROM carteira_movimentacoes
             WHERE tipo = 'consumo' AND modulo_key = :mk AND referencia_id = :ref
             ORDER BY id DESC LIMIT 1",
            ['mk' => $moduloKey, 'ref' => $ref]
        );
        if ($mov) {
            $this->estornar((int) $mov['id']);
        }
    }

    public function adicionarCreditos(
        string $userType,
        int $userId,
        float $valor,
        string $tipo,
        ?string $moduloKey = null,
        $referenciaId = null
    ): void {
        $this->assertUserType($userType, true);
        $tiposValidos = ['recarga_mensal', 'cortesia', 'compra', 'recarga_plano', 'recarga_inicial'];
        if (!in_array($tipo, $tiposValidos, true)) {
            throw new \InvalidArgumentException('Tipo de movimentação inválido para adicionar créditos.');
        }
        $valor = round($valor, \CreditosDecimalHelper::SCALE);
        if ($valor <= 0) {
            return;
        }

        $this->ensureWalletSchema();
        $origem = in_array($tipo, ['compra', 'recarga_plano'], true) ? 'comprado' : 'escola';
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) {
            $this->db->beginTransaction();
        }
        try {
            $row = $this->db->fetch(
                "SELECT saldo_escola, saldo_comprado
                 FROM carteira_usuarios
                 WHERE user_type = :ut AND user_id = :uid
                 FOR UPDATE",
                ['ut' => $userType, 'uid' => $userId]
            );
            if ($row === null) {
                $this->db->insert(
                    "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado)
                     VALUES (:ut, :uid, 0, 0, 0)",
                    ['ut' => $userType, 'uid' => $userId]
                );
                $saldoEscola = 0.0;
                $saldoComprado = 0.0;
            } else {
                $saldoEscola = \CreditosDecimalHelper::fromScalar($row['saldo_escola'] ?? 0, 0.0);
                $saldoComprado = \CreditosDecimalHelper::fromScalar($row['saldo_comprado'] ?? 0, 0.0);
            }

            if ($origem === 'comprado') {
                $saldoComprado = round($saldoComprado + $valor, \CreditosDecimalHelper::SCALE);
            } else {
                $saldoEscola = round($saldoEscola + $valor, \CreditosDecimalHelper::SCALE);
            }

            $this->syncWalletSaldos($userType, $userId, $saldoEscola, $saldoComprado);

            $ref = $referenciaId !== null ? (string) $referenciaId : null;
            $this->db->insert(
                "INSERT INTO carteira_movimentacoes (user_type, user_id, tipo, saldo_origem, valor, modulo_key, referencia_id)
                 VALUES (:ut, :uid, :tipo, :origem, :valor, :mk, :ref)",
                [
                    'ut' => $userType,
                    'uid' => $userId,
                    'tipo' => $tipo,
                    'origem' => $origem,
                    'valor' => $valor,
                    'mk' => $moduloKey,
                    'ref' => $ref,
                ]
            );

            if ($ownTx) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    public function aplicarRecargaInicialSeAplicavel(string $userType, int $userId): void
    {
        $this->assertUserType($userType);
        if (!$this->isCreditosHabilitado()) {
            return;
        }
        $key = $userType === 'aluno' ? 'creditos_mensal_aluno' : 'creditos_mensal_professor';
        $raw = \LayoutHelper::get($key, 0);
        $valor = is_numeric($raw) ? round((float) $raw, \CreditosDecimalHelper::SCALE) : 0.0;
        if ($valor <= 0) {
            return;
        }
        $wallet = $this->getWalletSaldos($userType, $userId);
        if ($wallet['saldo_total'] > 0) {
            return;
        }
        $jaRecebeu = $this->db->fetch(
            "SELECT id FROM carteira_movimentacoes
             WHERE user_type = :ut AND user_id = :uid AND tipo = 'recarga_inicial'
             LIMIT 1",
            ['ut' => $userType, 'uid' => $userId]
        );
        if ($jaRecebeu !== null) {
            return;
        }
        $this->adicionarCreditos($userType, $userId, $valor, 'recarga_inicial', null, null);
    }

    public function getMovimentacoes(string $userType, int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->getMovimentacoesFiltradas($userType, $userId, $limit, $offset, null, null, null, null);
    }

    public function getMovimentacoesFiltradas(
        string $userType,
        int $userId,
        int $limit = 50,
        int $offset = 0,
        ?string $tipo = null,
        ?string $moduloKey = null,
        ?string $dataIni = null,
        ?string $dataFim = null
    ): array {
        $this->assertUserType($userType, true);
        $this->ensureWalletSchema();
        $sql = "SELECT id, tipo, saldo_origem, valor, modulo_key, referencia_id, observacao, created_at
                FROM carteira_movimentacoes
                WHERE user_type = :ut AND user_id = :uid";
        $params = ['ut' => $userType, 'uid' => $userId];
        if ($tipo !== null && $tipo !== '') {
            $sql .= " AND tipo = :tipo";
            $params['tipo'] = $tipo;
        }
        if ($moduloKey !== null && $moduloKey !== '') {
            $sql .= " AND modulo_key = :mk";
            $params['mk'] = $moduloKey;
        }
        if ($dataIni !== null && $dataIni !== '') {
            $sql .= " AND DATE(created_at) >= :dini";
            $params['dini'] = $dataIni;
        }
        if ($dataFim !== null && $dataFim !== '') {
            $sql .= " AND DATE(created_at) <= :dfim";
            $params['dfim'] = $dataFim;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        return $this->db->fetchAll($sql, $params);
    }

    public function countMovimentacoesFiltradas(
        string $userType,
        int $userId,
        ?string $tipo = null,
        ?string $moduloKey = null,
        ?string $dataIni = null,
        ?string $dataFim = null
    ): int {
        $this->assertUserType($userType, true);
        $this->ensureWalletSchema();
        $sql = "SELECT COUNT(*) AS total
                FROM carteira_movimentacoes
                WHERE user_type = :ut AND user_id = :uid";
        $params = ['ut' => $userType, 'uid' => $userId];
        if ($tipo !== null && $tipo !== '') {
            $sql .= " AND tipo = :tipo";
            $params['tipo'] = $tipo;
        }
        if ($moduloKey !== null && $moduloKey !== '') {
            $sql .= " AND modulo_key = :mk";
            $params['mk'] = $moduloKey;
        }
        if ($dataIni !== null && $dataIni !== '') {
            $sql .= " AND DATE(created_at) >= :dini";
            $params['dini'] = $dataIni;
        }
        if ($dataFim !== null && $dataFim !== '') {
            $sql .= " AND DATE(created_at) <= :dfim";
            $params['dfim'] = $dataFim;
        }
        $row = $this->db->fetch($sql, $params);

        return (int) ($row['total'] ?? 0);
    }

    public function listarTabelaPrecosModulos(): array
    {
        $out = [];
        foreach (\CreditosModuleRegistry::getModuleKeys() as $key) {
            $out[] = [
                'modulo_key' => $key,
                'label' => \CreditosModuleRegistry::getLabel($key),
                'grupo' => \CreditosModuleRegistry::getGrupo($key),
                'cobra' => $this->moduloCobraCredito($key),
                'custo' => $this->getCustoModulo($key),
            ];
        }
        return $out;
    }

    private function syncWalletSaldos(string $userType, int $userId, float $saldoEscola, float $saldoComprado): void
    {
        $this->ensureWalletSchema();
        $saldoEscola = round(max(0.0, $saldoEscola), \CreditosDecimalHelper::SCALE);
        $saldoComprado = round(max(0.0, $saldoComprado), \CreditosDecimalHelper::SCALE);
        $saldoTotal = round($saldoEscola + $saldoComprado, \CreditosDecimalHelper::SCALE);
        $this->db->query(
            "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado)
             VALUES (:ut, :uid, :saldo_total, :saldo_escola, :saldo_comprado)
             ON DUPLICATE KEY UPDATE
                saldo = VALUES(saldo),
                saldo_escola = VALUES(saldo_escola),
                saldo_comprado = VALUES(saldo_comprado),
                updated_at = NOW()",
            [
                'saldo_total' => $saldoTotal,
                'saldo_escola' => $saldoEscola,
                'saldo_comprado' => $saldoComprado,
                'ut' => $userType,
                'uid' => $userId,
            ]
        );
    }

    private function ensureWalletSchema(): void
    {
        static $checkedSchemas = [];
        try {
            $schemaRow = $this->db->fetch('SELECT DATABASE() AS db_name');
            $schemaKey = trim((string) ($schemaRow['db_name'] ?? ''));
        } catch (\Throwable $e) {
            $schemaKey = '';
        }
        if ($schemaKey !== '' && isset($checkedSchemas[$schemaKey])) {
            return;
        }
        if ($schemaKey !== '') {
            $checkedSchemas[$schemaKey] = true;
        }

        try {
            $saldoEscolaCol = $this->db->fetch(
                "SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'carteira_usuarios'
                   AND COLUMN_NAME = 'saldo_escola'
                 LIMIT 1"
            );
            if (!$saldoEscolaCol) {
                $this->db->query(
                    "ALTER TABLE carteira_usuarios
                     ADD COLUMN saldo_escola DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo"
                );
                $this->db->query("UPDATE carteira_usuarios SET saldo_escola = COALESCE(saldo, 0)");
            }
        } catch (\Throwable $e) {
        }

        try {
            $saldoCompradoCol = $this->db->fetch(
                "SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'carteira_usuarios'
                   AND COLUMN_NAME = 'saldo_comprado'
                 LIMIT 1"
            );
            if (!$saldoCompradoCol) {
                $this->db->query(
                    "ALTER TABLE carteira_usuarios
                     ADD COLUMN saldo_comprado DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER saldo_escola"
                );
            }
        } catch (\Throwable $e) {
        }

        try {
            $origemCol = $this->db->fetch(
                "SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'carteira_movimentacoes'
                   AND COLUMN_NAME = 'saldo_origem'
                 LIMIT 1"
            );
            if (!$origemCol) {
                $this->db->query(
                    "ALTER TABLE carteira_movimentacoes
                     ADD COLUMN saldo_origem ENUM('escola','comprado','misto') NULL DEFAULT NULL AFTER tipo"
                );
                $this->db->query(
                    "UPDATE carteira_movimentacoes
                     SET saldo_origem = CASE
                        WHEN tipo IN ('compra', 'recarga_plano') THEN 'comprado'
                        WHEN tipo IN ('recarga_mensal', 'recarga_inicial', 'cortesia') THEN 'escola'
                        WHEN tipo IN ('consumo', 'estorno') THEN 'misto'
                        ELSE NULL
                     END
                     WHERE saldo_origem IS NULL"
                );
            }
        } catch (\Throwable $e) {
        }

        try {
            $enumRow = $this->db->fetch(
                "SELECT COLUMN_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'carteira_usuarios'
                   AND COLUMN_NAME = 'user_type'
                 LIMIT 1"
            );
            $colType = strtolower((string) ($enumRow['COLUMN_TYPE'] ?? ''));
            if ($colType !== '' && strpos($colType, 'escola') === false) {
                $this->db->query(
                    "ALTER TABLE carteira_usuarios
                     MODIFY COLUMN user_type ENUM('aluno','professor','escola') NOT NULL"
                );
                $this->db->query(
                    "ALTER TABLE carteira_movimentacoes
                     MODIFY COLUMN user_type ENUM('aluno','professor','escola') NOT NULL"
                );
            }
            $existe = $this->db->fetch(
                "SELECT id FROM carteira_usuarios WHERE user_type = 'escola' AND user_id = :uid LIMIT 1",
                ['uid' => \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID]
            );
            if (!$existe) {
                $this->db->insert(
                    "INSERT INTO carteira_usuarios (user_type, user_id, saldo, saldo_escola, saldo_comprado)
                     VALUES ('escola', :uid, 0, 0, 0)",
                    ['uid' => \CreditosModuleRegistry::ESCOLA_CARTEIRA_USER_ID]
                );
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param bool $allowEscola permite user_type=escola (carteira institucional)
     */
    private function assertUserType(string $userType, bool $allowEscola = false): void
    {
        $ok = $userType === 'aluno' || $userType === 'professor' || ($allowEscola && $userType === 'escola');
        if (!$ok) {
            throw new \InvalidArgumentException('userType deve ser aluno, professor' . ($allowEscola ? ' ou escola' : '') . '.');
        }
    }
}
}
