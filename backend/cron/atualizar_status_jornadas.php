<?php
/**
 * Script de Cron Job para Atualizar Status das Jornadas
 *
 * Analisa a data/hora que está no banco (estrutura.data_inicio, data_fim, hora_inicio, hora_fim)
 * e atualiza o status da jornada mesmo que já tenha passado alguma data (ex.: libera para
 * em_andamento quando passa o horário de início, ou marca finalizada quando passa o fim).
 *
 * Status: aguardando | em_andamento | concluído (coluna: finalizada)
 *
 * Configuração do Cron (recomendado a cada hora):
 * 0 * * * * /usr/bin/php /caminho/para/projeto/cron/atualizar_status_jornadas.php
 */

// Detectar se está sendo executado via CLI ou web
$isCli = php_sapi_name() === 'cli';

// Se executado via web, habilitar exibição de erros
if (!$isCli) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre style='font-family: monospace; font-size: 12px; background: #f5f5f5; padding: 20px; border: 1px solid #ddd;'>\n";
    echo "<h2>Script de Atualização de Status das Jornadas</h2>\n";
    echo "<hr>\n";
}

// Definir caminho base do projeto
if ($isCli) {
    $basePath = dirname(__DIR__);
} else {
    // Quando executado via web, usar caminho absoluto baseado no script atual
    $basePath = dirname(dirname(__FILE__));
}

// Exibir informações de debug
if (!$isCli) {
    echo "Modo: Web\n";
    echo "Caminho base: $basePath\n";
    echo "Script atual: " . __FILE__ . "\n";
    echo "Diretório do script: " . dirname(__FILE__) . "\n";
    echo "<hr>\n";
}

// Verificar se os arquivos necessários existem
$vendorPath = $basePath . '/vendor/autoload.php';
$configPath = $basePath . '/config/app.php';
$databasePath = $basePath . '/app/Core/Database.php';

if (!$isCli) {
    echo "Verificando arquivos necessários...\n";
    echo "vendor/autoload.php: " . (file_exists($vendorPath) ? "✓ Encontrado" : "✗ NÃO ENCONTRADO") . " ($vendorPath)\n";
    echo "config/app.php: " . (file_exists($configPath) ? "✓ Encontrado" : "✗ NÃO ENCONTRADO") . " ($configPath)\n";
    echo "app/Core/Database.php: " . (file_exists($databasePath) ? "✓ Encontrado" : "✗ NÃO ENCONTRADO") . " ($databasePath)\n";
    echo "<hr>\n";
}

if (!file_exists($vendorPath)) {
    $error = "ERRO: Arquivo vendor/autoload.php não encontrado em: $vendorPath";
    if (!$isCli) {
        echo "<strong style='color: red;'>$error</strong>\n";
        echo "</pre>";
    }
    die($error . "\n");
}

if (!file_exists($configPath)) {
    $error = "ERRO: Arquivo config/app.php não encontrado em: $configPath";
    if (!$isCli) {
        echo "<strong style='color: red;'>$error</strong>\n";
        echo "</pre>";
    }
    die($error . "\n");
}

if (!file_exists($databasePath)) {
    $error = "ERRO: Arquivo app/Core/Database.php não encontrado em: $databasePath";
    if (!$isCli) {
        echo "<strong style='color: red;'>$error</strong>\n";
        echo "</pre>";
    }
    die($error . "\n");
}

// Carregar autoload
if (!$isCli) {
    echo "Carregando arquivos...\n";
}

try {
    require_once $vendorPath;
    if (!$isCli) echo "✓ vendor/autoload.php carregado\n";
} catch (Exception $e) {
    $error = "ERRO ao carregar vendor/autoload.php: " . $e->getMessage();
    if (!$isCli) {
        echo "<strong style='color: red;'>$error</strong>\n";
        echo "</pre>";
    }
    die($error . "\n");
}

// Definir constantes necessárias antes de carregar config/app.php
if (!defined('FOLDER')) {
    if ($isCli) {
        define('FOLDER', '');
    } else {
        $scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $folder = ($scriptPath === '/' || $scriptPath === '') ? '' : $scriptPath;
        // Remover /cron do caminho se estiver presente
        $folder = str_replace('/cron', '', $folder);
        define('FOLDER', $folder);
    }
}

if (!defined('URL')) {
    if ($isCli) {
        define('URL', 'http://localhost');
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        define('URL', $protocol . '://' . $host . FOLDER);
    }
}

if (!defined('ENVIRONMENT')) {
    // Tentar ler do .env ou usar padrão
    $envFile = $basePath . '/.env';
    $environment = 'production'; // padrão seguro
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        if (preg_match('/ENVIRONMENT\s*=\s*(\w+)/', $envContent, $matches)) {
            $environment = trim($matches[1]);
        }
    }
    define('ENVIRONMENT', $environment);
}

if (!defined('DEBUG')) {
    define('DEBUG', ENVIRONMENT === 'development');
}

if (!$isCli) {
    echo "Constantes definidas:\n";
    echo "  FOLDER: " . FOLDER . "\n";
    echo "  URL: " . URL . "\n";
    echo "  ENVIRONMENT: " . ENVIRONMENT . "\n";
    echo "  DEBUG: " . (DEBUG ? 'true' : 'false') . "\n";
}

try {
    require_once $configPath;
    if (!$isCli) echo "✓ config/app.php carregado\n";
} catch (Exception $e) {
    $error = "ERRO ao carregar config/app.php: " . $e->getMessage();
    if (!$isCli) {
        echo "<strong style='color: red;'>$error</strong>\n";
        echo "</pre>";
    }
    die($error . "\n");
}

try {
    require_once $databasePath;
    if (!$isCli) echo "✓ app/Core/Database.php carregado\n";
} catch (Exception $e) {
    $error = "ERRO ao carregar app/Core/Database.php: " . $e->getMessage();
    if (!$isCli) {
        echo "<strong style='color: red;'>$error</strong>\n";
        echo "</pre>";
    }
    die($error . "\n");
}

$cronMultiTenantPath = $basePath . '/app/Core/cron_multi_tenant_helper.php';
if (file_exists($cronMultiTenantPath)) {
    require_once $cronMultiTenantPath;
}

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

if (!$isCli) {
    echo "<hr>\n";
}

// Criar diretório de logs se não existir
$logsDir = $basePath . '/storage/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

// Função para log
function logMessage($message, $basePath, $isCli = true) {
    $logFile = $basePath . '/storage/logs/cron_jornadas.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    // Tentar escrever no log
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Exibir mensagem
    if ($isCli) {
        echo $logMessage;
    } else {
        // Quando via web, já está dentro de <pre>, então apenas escapar HTML
        echo htmlspecialchars($logMessage);
    }
}

try {
    $runCron = function (?int $escolaId) use ($basePath, $isCli) {
        $db = Database::getInstance();
        $dataAtual = date('Y-m-d');

        // Evitar rodar no banco master (educa_master): tabela jornadas existe só nos bancos dos tenants
        try {
            $existe = $db->fetch(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'jornadas' LIMIT 1"
            );
            if (!$existe) {
                return;
            }
        } catch (Throwable $e) {
            return;
        }

        // Puxa TODAS as jornadas com estrutura (qualquer status: aguardando, em_andamento, finalizada, ativa, pausada).
        $jornadas = $db->fetchAll(
            "SELECT id, estrutura, titulo, status FROM jornadas WHERE estrutura IS NOT NULL AND TRIM(estrutura) != ''"
        );

        $totalJornadas = count($jornadas);

        if ($totalJornadas === 0) {
            return;
        }

        $atualizadas = 0;
        $aguardando = 0;
        $emAndamento = 0;
        $concluidas = 0;
        $semDatas = 0;

        $helperPath = $basePath . '/app/Core/JornadaStatusHelper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
        }

        foreach ($jornadas as $jornada) {
            try {
                $estrutura = json_decode($jornada['estrutura'], true) ?: [];
                $dataInicio = $estrutura['data_inicio'] ?? null;
                $dataFim = $estrutura['data_fim'] ?? null;
                if (!$dataInicio || !$dataFim) {
                    $semDatas++;
                    continue;
                }

                if (class_exists('JornadaStatusHelper')) {
                    $statusCalculado = JornadaStatusHelper::recalcularEPersistir(
                        $db,
                        (int) $jornada['id'],
                        $jornada['estrutura'],
                        $jornada['status'] ?? null
                    );
                } else {
                    $dataAtual = date('Y-m-d');
                    if ($dataAtual < $dataInicio) {
                        $statusCalculado = 'aguardando';
                    } elseif ($dataAtual > $dataFim) {
                        $statusCalculado = 'concluido';
                    } else {
                        $statusCalculado = 'em_andamento';
                    }
                    $estrutura['status_jornada'] = $statusCalculado;
                    $estruturaAtualizada = json_encode($estrutura, JSON_UNESCAPED_UNICODE);
                    if ($statusCalculado === 'concluido') {
                        $db->update("UPDATE jornadas SET estrutura = :estrutura, status = 'finalizada', updated_at = NOW() WHERE id = :id", ['estrutura' => $estruturaAtualizada, 'id' => $jornada['id']]);
                    } else {
                        $db->update("UPDATE jornadas SET estrutura = :estrutura, updated_at = NOW() WHERE id = :id", ['estrutura' => $estruturaAtualizada, 'id' => $jornada['id']]);
                    }
                }

                if ($statusCalculado === 'aguardando') {
                    $aguardando++;
                } elseif ($statusCalculado === 'em_andamento') {
                    $emAndamento++;
                } else {
                    $concluidas++;
                }
                $atualizadas++;
            } catch (Exception $e) {
                logMessage("ERRO ao processar jornada #{$jornada['id']}: " . $e->getMessage(), $basePath, $isCli);
                continue;
            }
        }
    };

    // forceIterateTenants=true: sempre executa nos bancos das escolas (mesmo com MULTI_TENANT=false no master)
    if (class_exists('CronMultiTenantHelper')) {
        CronMultiTenantHelper::run($runCron, true);
    } else {
        $runCron(null);
    }

} catch (Exception $e) {
    $errorMsg = "ERRO CRÍTICO: " . $e->getMessage();
    $errorFile = "Arquivo: " . $e->getFile() . " | Linha: " . $e->getLine();
    $errorTrace = "Stack trace: " . $e->getTraceAsString();
    
    logMessage($errorMsg, $basePath, $isCli);
    logMessage($errorFile, $basePath, $isCli);
    logMessage($errorTrace, $basePath, $isCli);
    
    if (!$isCli) {
        echo "<hr>\n";
        echo "<h3 style='color: red;'>ERRO CRÍTICO</h3>\n";
        echo "<strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "\n";
        echo "<strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "\n";
        echo "<strong>Linha:</strong> " . $e->getLine() . "\n";
        echo "<strong>Stack Trace:</strong>\n";
        echo "<pre style='background: #fff; padding: 10px; border: 1px solid #ccc; overflow-x: auto;'>";
        echo htmlspecialchars($e->getTraceAsString());
        echo "</pre>\n";
        echo "</pre>";
        http_response_code(500);
    }
    exit(1);
}

if (!$isCli) {
    echo "<hr>\n";
    echo "<strong style='color: green;'>✓ Script executado com sucesso!</strong>\n";
    echo "</pre>";
    http_response_code(200);
}
exit(0);

