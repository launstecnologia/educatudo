<?php
/**
 * EducaTudo - Classe para Gerenciar Webhooks
 * Gerencia comunicação com webhooks externos
 */

class WebhookManager
{
    private $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Busca webhook para chat IA
     */
    public function getChatWebhook($escola_id = null)
    {
        // Prioridade 1: Webhook específico da escola
        if ($escola_id) {
            $webhook = $this->db->fetch(
                "SELECT * FROM webhooks 
                 WHERE escola_id = :escola_id 
                 AND tipo IN ('chat_ia', 'chat') 
                 AND ativo = 1 
                 ORDER BY tipo = 'chat_ia' DESC 
                 LIMIT 1",
                ['escola_id' => $escola_id]
            );
            
            if ($webhook) {
                return $webhook;
            }
        }
        
        // Prioridade 2: Webhook global
        $webhook = $this->db->fetch(
            "SELECT * FROM webhooks 
             WHERE escola_id IS NULL 
             AND tipo IN ('chat_ia', 'chat') 
             AND ativo = 1 
             ORDER BY tipo = 'chat_ia' DESC 
             LIMIT 1"
        );
        
        return $webhook;
    }
    
    /**
     * Envia mensagem para o webhook
     */
    public function sendChatMessage($user_id, $chat_id, $nome, $message, $imageUrl = null)
    {
        $webhook = $this->getChatWebhook();
        
        if (!$webhook) {
            throw new Exception('Nenhum webhook de chat configurado');
        }
        
        // Preparar payload
        $payload = [
            'user_id' => $user_id,
            'chat_id' => $chat_id,
            'nome' => $nome,
            'message' => $message
        ];
        
        if ($imageUrl) {
            $payload['imageUrl'] = $imageUrl;
        }
        
        // Enviar requisição
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook['endpoint']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: EducaTudo-Chat/1.0'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);  // Aumentado de 30 para 120 segundos
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log detalhado da resposta do webhook
        $this->logWebhookResponse($webhook['endpoint'], $payload, $response, $httpCode, $error);
        
        if ($error) {
            throw new Exception("Erro cURL: {$error}");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("Erro HTTP {$httpCode}: {$response}");
        }
        
        return $this->parseResponse($response);
    }
    
    /**
     * Processa a resposta do webhook
     */
    private function parseResponse($response)
    {
        // Tentar parse JSON primeiro
        $jsonResponse = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonResponse)) {
            // Buscar mensagem nas chaves de prioridade
            $messageKeys = ['response', 'message', 'output', 'content', 'text', 'result'];
            
            foreach ($messageKeys as $key) {
                if (isset($jsonResponse[$key]) && !empty($jsonResponse[$key])) {
                    return $this->preserveHtmlContent($jsonResponse[$key]);
                }
            }
        }
        
        // Se não for JSON válido ou não encontrar mensagem, usar texto bruto
        return $this->preserveHtmlContent($response);
    }
    
    /**
     * Preserva conteúdo HTML quando necessário
     */
    private function preserveHtmlContent($content)
    {
        // Se contém iframe com srcdoc, extrair o conteúdo HTML rico
        if (preg_match('/srcdoc=["\']([^"\']+)["\']/', $content, $matches)) {
            $srcdocContent = html_entity_decode($matches[1]);
            
            // Verificar se o conteúdo do srcdoc é HTML rico
            $htmlPatterns = [
                '/<h[1-6][^>]*>/i',           // Headers
                '/<p[^>]*>/i',                // Parágrafos
                '/<ul[^>]*>/i',               // Listas não ordenadas
                '/<ol[^>]*>/i',               // Listas ordenadas
                '/<li[^>]*>/i',               // Itens de lista
                '/<strong[^>]*>/i',            // Negrito
                '/<em[^>]*>/i',               // Itálico
                '/<sub[^>]*>/i',              // Subscrito
                '/<sup[^>]*>/i',              // Sobrescrito
            ];
            
            $isRichHtml = false;
            foreach ($htmlPatterns as $pattern) {
                if (preg_match($pattern, $srcdocContent)) {
                    $isRichHtml = true;
                    break;
                }
            }
            
            if ($isRichHtml) {
                // É HTML rico - preservar e normalizar apenas quebras de linha
                $srcdocContent = html_entity_decode($srcdocContent, ENT_QUOTES, 'UTF-8');
                $srcdocContent = preg_replace('/\r\n|\r|\n/', "\n", $srcdocContent);
                $srcdocContent = preg_replace('/\n{3,}/', "\n\n", $srcdocContent);
                return trim($srcdocContent);
            } else {
                // É texto simples - usar processamento normal
                return $this->normalizeMessage($this->stripHtmlTags($srcdocContent));
            }
        }
        
        // Verificar se é HTML rico (contém tags de formatação)
        $htmlPatterns = [
            '/<h[1-6][^>]*>/i',           // Headers
            '/<p[^>]*>/i',                // Parágrafos
            '/<ul[^>]*>/i',               // Listas não ordenadas
            '/<ol[^>]*>/i',               // Listas ordenadas
            '/<li[^>]*>/i',               // Itens de lista
            '/<strong[^>]*>/i',            // Negrito
            '/<em[^>]*>/i',               // Itálico
            '/<sub[^>]*>/i',              // Subscrito
            '/<sup[^>]*>/i',              // Sobrescrito
        ];
        
        $isRichHtml = false;
        foreach ($htmlPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $isRichHtml = true;
                break;
            }
        }
        
        if ($isRichHtml) {
            // É HTML rico - preservar e normalizar apenas quebras de linha
            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
            $content = preg_replace('/\r\n|\r|\n/', "\n", $content);
            $content = preg_replace('/\n{3,}/', "\n\n", $content);
            return trim($content);
        } else {
            // É texto simples - usar processamento normal
            return $this->normalizeMessage($this->extractTextFromHtml($content));
        }
    }
    
    /**
     * Extrai apenas o texto de conteúdo HTML
     */
    private function extractTextFromHtml($html)
    {
        // Se contém iframe com srcdoc, extrair o conteúdo
        if (preg_match('/srcdoc=["\']([^"\']+)["\']/', $html, $matches)) {
            $srcdocContent = html_entity_decode($matches[1]);
            return $this->stripHtmlTags($srcdocContent);
        }
        
        // Se é HTML puro, extrair apenas o texto
        return $this->stripHtmlTags($html);
    }
    
    /**
     * Remove tags HTML e mantém apenas o texto
     */
    private function stripHtmlTags($html)
    {
        // Decodificar entidades HTML
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        
        // Remover tags HTML mas manter quebras de linha
        $text = strip_tags($html, '<br><p>');
        
        // Converter <br> e </p> para quebras de linha
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n", $text);
        $text = preg_replace('/<p[^>]*>/i', '', $text);
        
        // Adicionar quebra de linha após títulos (h1, h2, h3, etc.)
        $text = preg_replace('/<\/h[1-6]>/i', "\n", $text);
        $text = preg_replace('/<h[1-6][^>]*>/i', '', $text);
        
        // Limpar espaços extras e quebras de linha
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = preg_replace('/\s+/', ' ', $text); // Múltiplos espaços para um só
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Normaliza quebras de linha na mensagem
     */
    private function normalizeMessage($message)
    {
        if (!is_string($message)) {
            $message = (string) $message;
        }
        
        // Converter quebras de linha
        $message = str_replace('\\n\\n', "\n\n", $message);
        $message = str_replace('\\n', "\n", $message);
        
        return trim($message);
    }
    
    /**
     * Lista todos os webhooks
     */
    public function getAllWebhooks()
    {
        return $this->db->fetchAll(
            "SELECT * FROM webhooks ORDER BY escola_id IS NULL DESC, tipo, nome"
        );
    }
    
    /**
     * Cria novo webhook
     */
    public function createWebhook($nome, $endpoint, $tipo, $escola_id = null, $configuracao = null)
    {
        return $this->db->insert(
            "INSERT INTO webhooks (nome, endpoint, tipo, escola_id, configuracao, ativo) 
             VALUES (:nome, :endpoint, :tipo, :escola_id, :configuracao, 1)",
            [
                'nome' => $nome,
                'endpoint' => $endpoint,
                'tipo' => $tipo,
                'escola_id' => $escola_id,
                'configuracao' => $configuracao ? json_encode($configuracao) : null
            ]
        );
    }
    
    /**
     * Atualiza webhook
     */
    public function updateWebhook($id, $nome, $endpoint, $tipo, $escola_id = null, $configuracao = null, $ativo = true)
    {
        return $this->db->update(
            "UPDATE webhooks SET 
                nome = :nome, 
                endpoint = :endpoint, 
                tipo = :tipo, 
                escola_id = :escola_id, 
                configuracao = :configuracao, 
                ativo = :ativo,
                updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $id,
                'nome' => $nome,
                'endpoint' => $endpoint,
                'tipo' => $tipo,
                'escola_id' => $escola_id,
                'configuracao' => $configuracao ? json_encode($configuracao) : null,
                'ativo' => $ativo ? 1 : 0
            ]
        );
    }
    
    /**
     * Deleta webhook
     */
    public function deleteWebhook($id)
    {
        return $this->db->delete("DELETE FROM webhooks WHERE id = :id", ['id' => $id]);
    }
    
    /**
     * Testa webhook
     */
    public function testWebhook($webhook_id)
    {
        $webhook = $this->db->fetch("SELECT * FROM webhooks WHERE id = :id", ['id' => $webhook_id]);
        
        if (!$webhook) {
            throw new Exception('Webhook não encontrado');
        }
        
        // Payload de teste
        $payload = [
            'user_id' => 'test_user',
            'chat_id' => 'test_chat_' . time(),
            'nome' => 'Usuário Teste',
            'message' => 'Esta é uma mensagem de teste do EducaTudo'
        ];
        
        try {
            $response = $this->sendChatMessage(
                $payload['user_id'],
                $payload['chat_id'],
                $payload['nome'],
                $payload['message']
            );
            
            return [
                'success' => true,
                'response' => $response,
                'webhook' => $webhook
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'webhook' => $webhook
            ];
        }
    }
    
    /**
     * Log detalhado da resposta do webhook
     */
    private function logWebhookResponse($endpoint, $payload, $response, $httpCode, $error)
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/webhook_responses.log';
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $endpoint,
            'payload' => $payload,
            'response_raw' => $response,
            'response_length' => strlen($response),
            'http_code' => $httpCode,
            'error' => $error,
            'response_preview' => substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''),
            'is_json' => json_decode($response, true) !== null,
            'contains_html' => preg_match('/<[^>]+>/', $response) === 1,
            'html_tags' => $this->extractHtmlTags($response)
        ];
        
        $logEntry = "=== WEBHOOK RESPONSE LOG ===\n";
        $logEntry .= "Timestamp: " . $logData['timestamp'] . "\n";
        $logEntry .= "Endpoint: " . $logData['endpoint'] . "\n";
        $logEntry .= "HTTP Code: " . $logData['http_code'] . "\n";
        $logEntry .= "Error: " . ($logData['error'] ?: 'None') . "\n";
        $logEntry .= "Response Length: " . $logData['response_length'] . " bytes\n";
        $logEntry .= "Is JSON: " . ($logData['is_json'] ? 'Yes' : 'No') . "\n";
        $logEntry .= "Contains HTML: " . ($logData['contains_html'] ? 'Yes' : 'No') . "\n";
        $logEntry .= "HTML Tags Found: " . implode(', ', $logData['html_tags']) . "\n";
        $logEntry .= "Payload Sent:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
        $logEntry .= "Response Preview:\n" . $logData['response_preview'] . "\n";
        $logEntry .= "Full Response:\n" . $response . "\n";
        $logEntry .= "=== END LOG ===\n\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Extrai tags HTML encontradas na resposta
     */
    private function extractHtmlTags($html)
    {
        preg_match_all('/<([a-zA-Z][a-zA-Z0-9]*)[^>]*>/', $html, $matches);
        $tags = array_unique($matches[1]);
        return array_slice($tags, 0, 10); // Limitar a 10 tags para não poluir o log
    }
}
