<?php
/**
 * EducaTudo - Serviço de Processamento de Arquivos
 * Processa PDFs, imagens e outros arquivos para o chat
 */

namespace App\Services;

use Exception;

class FileProcessorService
{
    private $openAIService;
    private $uploadDir;
    private $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    public function __construct()
    {
        require_once __DIR__ . '/OpenAIService.php';
        $this->openAIService = new OpenAIService();
        $this->uploadDir = __DIR__ . '/../../storage/chat/files/';
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Processa um arquivo enviado (PDF, imagem, etc) e extrai conteúdo
     * 
     * @param array $file Arquivo do $_FILES
     * @return array ['texto' => string, 'tipo' => string, 'url' => string]
     */
    public function processarArquivo($file)
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Arquivo inválido');
        }
        
        // Validar tamanho
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('Arquivo muito grande. Máximo: 10MB');
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'docx', 'txt'];
        
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception('Tipo de arquivo não suportado. Tipos aceitos: ' . implode(', ', $allowedTypes));
        }
        
        // Salvar arquivo
        $filename = 'file_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $this->uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Erro ao salvar arquivo');
        }
        
        $url = URL . '/storage/chat/files/' . $filename;
        
        // Extrair conteúdo baseado no tipo
        $texto = '';
        $tipo = $extension;
        
        switch ($extension) {
            case 'pdf':
                $texto = $this->extrairTextoPDF($filepath);
                break;
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
                $texto = $this->extrairTextoImagem($filepath);
                break;
            case 'docx':
                $texto = $this->extrairTextoDocx($filepath);
                break;
            case 'txt':
                $texto = file_get_contents($filepath);
                break;
        }
        
        return [
            'texto' => $texto,
            'tipo' => $tipo,
            'url' => $url,
            'filename' => $filename,
            'original_name' => $file['name']
        ];
    }
    
    /**
     * Extrai texto de PDF usando OpenAI Vision ou OCR
     */
    private function extrairTextoPDF($filepath)
    {
        // Converter primeira página do PDF em imagem e usar OCR
        // Para implementação completa, seria necessário usar biblioteca como FPDI ou similar
        // Por enquanto, retornar mensagem indicando que precisa ser processado
        return "Arquivo PDF recebido. Conteúdo será analisado pela IA.";
    }
    
    /**
     * Extrai texto de imagem usando OpenAI Vision
     */
    private function extrairTextoImagem($filepath)
    {
        try {
            $imageData = base64_encode(file_get_contents($filepath));
            $prompt = "Extraia todo o texto visível nesta imagem. Se houver fórmulas matemáticas, descreva-as claramente. Se houver gráficos ou diagramas, descreva-os detalhadamente.";
            
            return $this->openAIService->analyzeImage($imageData, $prompt);
        } catch (Exception $e) {
            error_log("Erro ao extrair texto de imagem: " . $e->getMessage());
            return "Erro ao processar imagem: " . $e->getMessage();
        }
    }
    
    /**
     * Extrai texto de DOCX
     */
    private function extrairTextoDocx($filepath)
    {
        // Para DOCX, seria necessário usar biblioteca como PhpOffice/PhpWord
        // Por enquanto, retornar mensagem
        return "Arquivo DOCX recebido. Conteúdo será analisado pela IA.";
    }
}

