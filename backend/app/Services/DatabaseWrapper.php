<?php
/**
 * EducaTudo - Wrapper de Banco de Dados com Métricas
 * Extende funcionalidade do Database com monitoramento de performance
 */

class DatabaseWrapper
{
    private $database;
    private static $instance = null;

    private function __construct()
    {
        // Carregar classe Database se necessário
        if (!class_exists('Database', false)) {
            require_once __DIR__ . '/../Core/Database.php';
        }

        $this->database = Database::getInstance();
    }

    /**
     * Singleton - retorna instância única
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Executa query preparada com métricas
     *
     * @param string $sql Query SQL
     * @param array $params Parâmetros da query
     * @return PDOStatement Resultado da query
     */
    public function query($sql, $params = [])
    {
        $startTime = microtime(true);

        try {
            $result = $this->database->query($sql, $params);

            // Registrar métricas de sucesso
            $this->recordMetrics($sql, $params, $startTime, null);

            return $result;

        } catch (\Exception $e) {
            // Registrar métricas de erro
            $this->recordMetrics($sql, $params, $startTime, $e->getMessage());

            // Relançar a exceção
            throw $e;
        }
    }

    /**
     * Busca um registro com métricas
     *
     * @param string $sql Query SQL
     * @param array $params Parâmetros da query
     * @return mixed Resultado da busca
     */
    public function fetch($sql, $params = [])
    {
        $startTime = microtime(true);

        try {
            $result = $this->database->fetch($sql, $params);

            // Registrar métricas de sucesso
            $this->recordMetrics($sql, $params, $startTime, null);

            return $result;

        } catch (\Exception $e) {
            // Registrar métricas de erro
            $this->recordMetrics($sql, $params, $startTime, $e->getMessage());

            // Relançar a exceção
            throw $e;
        }
    }

    /**
     * Busca todos os registros com métricas
     *
     * @param string $sql Query SQL
     * @param array $params Parâmetros da query
     * @return array Resultado da busca
     */
    public function fetchAll($sql, $params = [])
    {
        $startTime = microtime(true);

        try {
            $result = $this->database->fetchAll($sql, $params);

            // Registrar métricas de sucesso
            $this->recordMetrics($sql, $params, $startTime, null);

            return $result;

        } catch (\Exception $e) {
            // Registrar métricas de erro
            $this->recordMetrics($sql, $params, $startTime, $e->getMessage());

            // Relançar a exceção
            throw $e;
        }
    }

    /**
     * Insere registro e retorna ID com métricas
     *
     * @param string $sql Query SQL
     * @param array $params Parâmetros da query
     * @return int ID do registro inserido
     */
    public function insert($sql, $params = [])
    {
        $startTime = microtime(true);

        try {
            $result = $this->database->insert($sql, $params);

            // Registrar métricas de sucesso
            $this->recordMetrics($sql, $params, $startTime, null);

            return $result;

        } catch (\Exception $e) {
            // Registrar métricas de erro
            $this->recordMetrics($sql, $params, $startTime, $e->getMessage());

            // Relançar a exceção
            throw $e;
        }
    }

    /**
     * Atualiza registro com métricas
     *
     * @param string $sql Query SQL
     * @param array $params Parâmetros da query
     * @return int Número de registros afetados
     */
    public function update($sql, $params = [])
    {
        $startTime = microtime(true);

        try {
            $result = $this->database->update($sql, $params);

            // Registrar métricas de sucesso
            $this->recordMetrics($sql, $params, $startTime, null);

            return $result;

        } catch (\Exception $e) {
            // Registrar métricas de erro
            $this->recordMetrics($sql, $params, $startTime, $e->getMessage());

            // Relançar a exceção
            throw $e;
        }
    }

    /**
     * Deleta registro com métricas
     *
     * @param string $sql Query SQL
     * @param array $params Parâmetros da query
     * @return int Número de registros afetados
     */
    public function delete($sql, $params = [])
    {
        $startTime = microtime(true);

        try {
            $result = $this->database->delete($sql, $params);

            // Registrar métricas de sucesso
            $this->recordMetrics($sql, $params, $startTime, null);

            return $result;

        } catch (\Exception $e) {
            // Registrar métricas de erro
            $this->recordMetrics($sql, $params, $startTime, $e->getMessage());

            // Relançar a exceção
            throw $e;
        }
    }

    /**
     * Inicia transação
     */
    public function beginTransaction()
    {
        return $this->database->beginTransaction();
    }

    /**
     * Confirma transação
     */
    public function commit()
    {
        return $this->database->commit();
    }

    /**
     * Desfaz transação
     */
    public function rollback()
    {
        return $this->database->rollback();
    }

    /**
     * Verifica se está em transação
     */
    public function inTransaction()
    {
        return $this->database->inTransaction();
    }

    /**
     * Executa script SQL
     */
    public function executeScript($sqlFile)
    {
        return $this->database->executeScript($sqlFile);
    }

    /**
     * Verifica se tabela existe
     */
    public function tableExists($tableName)
    {
        return $this->database->tableExists($tableName);
    }

    /**
     * Retorna informações da conexão
     */
    public function getConnectionInfo()
    {
        return $this->database->getConnectionInfo();
    }

    /**
     * Retorna instância PDO
     */
    public function getPdo()
    {
        return $this->database->getPdo();
    }

    /**
     * Registra métricas da query executada
     *
     * @param string $sql Query SQL
     * @param array $params Parâmetros da query
     * @param float $startTime Tempo inicial
     * @param string|null $error Mensagem de erro (se houver)
     */
    private function recordMetrics($sql, $params, $startTime, $error = null)
    {
        try {
            $queryTime = microtime(true) - $startTime;

            // Registrar métricas
            \App\Services\MetricsService::recordDatabaseMetrics([
                'query_time' => $queryTime,
                'error' => $error
            ]);

        } catch (\Exception $e) {
            // Não interromper execução em caso de erro no registro de métricas
            error_log("Erro ao registrar métricas DB: " . $e->getMessage());
        }
    }

    /**
     * Método estático para facilitar migração gradual
     * Permite usar DatabaseWrapper::query() ao invés de DatabaseWrapper::getInstance()->query()
     */
    public static function __callStatic($method, $args)
    {
        $instance = self::getInstance();
        return call_user_func_array([$instance, $method], $args);
    }
}
