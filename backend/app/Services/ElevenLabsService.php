<?php
/**
 * EducaTudo - Serviço ElevenLabs
 * Conversão de texto em voz e transcrição de voz usando ElevenLabs API
 */

namespace App\Services;

use Exception;

class ElevenLabsService
{
    private $apiKey;
    private $baseUrl = 'https://api.elevenlabs.io/v1';
    private $defaultVoiceId = '21m00Tcm4TlvDq8ikWAM'; // Rachel (voz feminina em inglês) - pode ser configurado
    
    public function __construct()
    {
        $this->loadApiKey();
    }
    
    /**
     * Carrega a chave da API do banco de dados ou .env
     */
    private function loadApiKey()
    {
        $apiKey = null;
        
        // PRIORIDADE 1: Tentar do banco de dados (config_layout)
        try {
            if (!class_exists('Database', false)) {
                require_once __DIR__ . '/../Core/Database.php';
            }
            $db = \Database::getInstance();
            $config = $db->fetch(
                "SELECT config_value FROM config_layout WHERE config_key = ?",
                ['elevenlabs_api_key']
            );
            
            if ($config && !empty($config['config_value'])) {
                $apiKey = $config['config_value'];
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar ElevenLabs key do banco: " . $e->getMessage());
        }
        
        // PRIORIDADE 2: Usar função env() do sistema se disponível
        if (empty($apiKey) && function_exists('env')) {
            $apiKey = env('ELEVENLABS_API_KEY', '');
        }
        
        // PRIORIDADE 3: Tentar do arquivo .env com leitura direta
        if (empty($apiKey)) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $content = @file_get_contents($envPath);
                if ($content !== false) {
                    $lines = explode("\n", $content);
                    foreach ($lines as $line) {
                        $line = trim($line, " \t\r\n");
                        if (strpos($line, 'ELEVENLABS_API_KEY=') === 0) {
                            $apiKey = trim(substr($line, strlen('ELEVENLABS_API_KEY=')), " \t\r\n\"'");
                            break;
                        }
                    }
                }
            }
        }
        
        // PRIORIDADE 4: Tentar getenv()
        if (empty($apiKey)) {
            $apiKey = getenv('ELEVENLABS_API_KEY');
        }
        
        // PRIORIDADE 5: Tentar $_ENV
        if (empty($apiKey)) {
            $apiKey = $_ENV['ELEVENLABS_API_KEY'] ?? null;
        }
        
        $this->apiKey = $apiKey ?: null;
    }
    
    /**
     * Converte texto em áudio (Text-to-Speech)
     * 
     * @param string $texto Texto para converter
     * @param string|null $voiceId ID da voz (opcional, usa padrão se não fornecido)
     * @param array $options Opções adicionais (stability, similarity_boost, etc)
     * @return string Caminho do arquivo de áudio gerado
     */
    public function textoParaVoz($texto, $voiceId = null, $options = [])
    {
        if (empty($this->apiKey)) {
            throw new Exception('ELEVENLABS_API_KEY não configurada. Configure via Admin > Dev Settings > Chaves de API ou no arquivo .env');
        }
        
        $voiceId = $voiceId ?? $this->defaultVoiceId;
        $stability = $options['stability'] ?? 0.5;
        $similarityBoost = $options['similarity_boost'] ?? 0.75;
        $style = $options['style'] ?? 0.0;
        $useSpeakerBoost = $options['use_speaker_boost'] ?? true;
        
        $data = [
            'text' => $texto,
            'model_id' => $options['model_id'] ?? 'eleven_multilingual_v2',
            'voice_settings' => [
                'stability' => $stability,
                'similarity_boost' => $similarityBoost,
                'style' => $style,
                'use_speaker_boost' => $useSpeakerBoost
            ]
        ];
        
        $ch = curl_init($this->baseUrl . '/text-to-speech/' . $voiceId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'xi-api-key: ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erro cURL: " . $error);
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($audioData, true);
            $errorMsg = $errorData['detail']['message'] ?? "Erro HTTP {$httpCode}";
            throw new Exception("Erro ao gerar áudio: " . $errorMsg);
        }
        
        // Salvar áudio em arquivo temporário
        $audioDir = __DIR__ . '/../../storage/audio/';
        if (!is_dir($audioDir)) {
            mkdir($audioDir, 0755, true);
        }
        
        $filename = 'tts_' . time() . '_' . uniqid() . '.mp3';
        $filepath = $audioDir . $filename;
        
        if (file_put_contents($filepath, $audioData) === false) {
            throw new Exception('Erro ao salvar arquivo de áudio');
        }
        
        return [
            'file_path' => $filepath,
            'url' => URL . '/storage/audio/' . $filename,
            'filename' => $filename
        ];
    }
    
    /**
     * Transcreve áudio em texto (Speech-to-Text)
     * 
     * @param string $audioFilePath Caminho do arquivo de áudio
     * @return string Texto transcrito
     */
    public function vozParaTexto($audioFilePath)
    {
        if (empty($this->apiKey)) {
            throw new Exception('ELEVENLABS_API_KEY não configurada');
        }
        
        if (!file_exists($audioFilePath)) {
            throw new Exception('Arquivo de áudio não encontrado');
        }
        
        // ElevenLabs não tem API de transcrição direta, usar OpenAI Whisper
        require_once __DIR__ . '/OpenAIService.php';
        $openaiService = new OpenAIService();
        
        return $openaiService->transcreverAudio($audioFilePath);
    }
    
    /**
     * Lista vozes disponíveis
     * 
     * @return array Lista de vozes
     */
    public function listarVozes()
    {
        if (empty($this->apiKey)) {
            throw new Exception('ELEVENLABS_API_KEY não configurada');
        }
        
        $ch = curl_init($this->baseUrl . '/voices');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'xi-api-key: ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erro cURL: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Erro HTTP {$httpCode} ao listar vozes");
        }
        
        $result = json_decode($response, true);
        return $result['voices'] ?? [];
    }
    
    /**
     * Retorna a chave da API
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }
}

