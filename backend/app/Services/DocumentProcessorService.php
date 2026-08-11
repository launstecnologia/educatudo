<?php
/**
 * EducaTudo - Serviço de Processamento de Documentos
 * Extrai texto de PDFs, DOCX, TXT e imagens
 */

/**
 * Serviço para processar e extrair texto de documentos
 * Suporta: PDF, DOCX, TXT, e imagens (OCR)
 */
class DocumentProcessorService
{
    private $uploadPath;
    private $maxFileSize;
    
    public function __construct()
    {
        $config = require __DIR__ . '/../../config/app.php';
        $this->uploadPath = $config['upload']['path'] . 'ai-agents/';
        $this->maxFileSize = $config['upload']['max_size'] ?? 10 * 1024 * 1024; // 10MB default
        
        // Cria diretório se não existir
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Valida e salva arquivo enviado
     */
    public function salvarArquivo($arquivo, $professorId, $agenteId)
    {
        if (!isset($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            throw new Exception('Arquivo inválido ou não enviado');
        }
        
        // Valida tamanho
        if ($arquivo['size'] > $this->maxFileSize) {
            throw new Exception('Arquivo muito grande. Tamanho máximo: ' . ($this->maxFileSize / 1024 / 1024) . 'MB');
        }
        
        // Valida tipo
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $tiposPermitidos = ['pdf', 'docx', 'doc', 'txt', 'md', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($extensao, $tiposPermitidos)) {
            throw new Exception('Tipo de arquivo não permitido. Tipos aceitos: ' . implode(', ', $tiposPermitidos));
        }
        
        // Cria estrutura de diretórios: storage/uploads/ai-agents/{professor_id}/{agente_id}/
        $diretorioDestino = $this->uploadPath . $professorId . '/' . $agenteId . '/';
        if (!is_dir($diretorioDestino)) {
            mkdir($diretorioDestino, 0755, true);
        }
        
        // Gera nome único
        $nomeArquivo = uniqid() . '_' . time() . '.' . $extensao;
        $caminhoCompleto = $diretorioDestino . $nomeArquivo;
        
        // Move arquivo
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            throw new Exception('Erro ao salvar arquivo');
        }
        
        return [
            'nome_arquivo' => $nomeArquivo,
            'nome_original' => $arquivo['name'],
            'caminho_completo' => $caminhoCompleto,
            'caminho_relativo' => 'ai-agents/' . $professorId . '/' . $agenteId . '/' . $nomeArquivo,
            'tipo_mime' => $arquivo['type'] ?? mime_content_type($caminhoCompleto),
            'tamanho_bytes' => $arquivo['size'],
            'extensao' => $extensao
        ];
    }
    
    /**
     * Extrai texto do documento baseado no tipo
     */
    public function extrairTexto($caminhoArquivo, $tipoMime = null)
    {
        if (!file_exists($caminhoArquivo)) {
            throw new Exception('Arquivo não encontrado');
        }
        
        $extensao = strtolower(pathinfo($caminhoArquivo, PATHINFO_EXTENSION));
        
        switch ($extensao) {
            case 'pdf':
                return $this->extrairTextoPDF($caminhoArquivo);
            
            case 'docx':
                return $this->extrairTextoDOCX($caminhoArquivo);
            
            case 'doc':
                return $this->extrairTextoDOC($caminhoArquivo);
            
            case 'txt':
            case 'md':
                return $this->extrairTextoSimples($caminhoArquivo);
            
            case 'jpg':
            case 'jpeg':
            case 'png':
                return $this->extrairTextoImagem($caminhoArquivo);
            
            default:
                throw new Exception('Tipo de arquivo não suportado para extração: ' . $extensao);
        }
    }
    
    /**
     * Extrai texto de PDF usando pdftotext ou fallback manual
     */
    private function extrairTextoPDF($caminhoArquivo)
    {
        // Tenta usar pdftotext (ferramenta do sistema) se disponível
        if (function_exists('shell_exec') && $this->comandoExiste('pdftotext')) {
            $comando = escapeshellarg($caminhoArquivo) . ' -';
            $texto = shell_exec("pdftotext $comando 2>&1");
            if ($texto && strlen(trim($texto)) > 0) {
                return $this->limparTexto($texto);
            }
        }
        
        // Fallback: usa biblioteca PHP (se disponível) ou retorna erro
        // Para produção, recomenda-se instalar: composer require smalot/pdfparser
        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($caminhoArquivo);
                $texto = $pdf->getText();
                return $this->limparTexto($texto);
            } catch (Exception $e) {
                error_log("Erro ao extrair PDF com PdfParser: " . $e->getMessage());
            }
        }
        
        throw new Exception('Não foi possível extrair texto do PDF. Instale pdftotext ou a biblioteca smalot/pdfparser.');
    }
    
    /**
     * Extrai texto de DOCX usando ZipArchive
     */
    private function extrairTextoDOCX($caminhoArquivo)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('Extensão ZipArchive não está disponível');
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($caminhoArquivo) !== TRUE) {
            throw new Exception('Não foi possível abrir o arquivo DOCX');
        }
        
        // DOCX armazena texto em word/document.xml
        $texto = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if (!$texto) {
            throw new Exception('Não foi possível extrair texto do DOCX');
        }
        
        // Remove tags XML e limpa
        $texto = strip_tags($texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
        
        return $this->limparTexto($texto);
    }
    
    /**
     * Extrai texto de DOC (formato antigo) - requer conversão ou biblioteca externa
     */
    private function extrairTextoDOC($caminhoArquivo)
    {
        // DOC é mais complexo, requer biblioteca externa ou conversão
        // Opção 1: Converter para DOCX usando LibreOffice (se disponível)
        if (function_exists('shell_exec') && $this->comandoExiste('libreoffice')) {
            $tempDir = sys_get_temp_dir() . '/doc_conv_' . uniqid();
            mkdir($tempDir, 0755, true);
            
            $comando = sprintf(
                'libreoffice --headless --convert-to docx --outdir %s %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($caminhoArquivo)
            );
            
            shell_exec($comando);
            
            $docxFile = $tempDir . '/' . pathinfo($caminhoArquivo, PATHINFO_FILENAME) . '.docx';
            if (file_exists($docxFile)) {
                $texto = $this->extrairTextoDOCX($docxFile);
                unlink($docxFile);
                rmdir($tempDir);
                return $texto;
            }
        }
        
        throw new Exception('Extração de DOC requer LibreOffice ou biblioteca externa. Recomenda-se converter para DOCX primeiro.');
    }
    
    /**
     * Extrai texto de arquivo simples (TXT, MD)
     */
    private function extrairTextoSimples($caminhoArquivo)
    {
        $texto = file_get_contents($caminhoArquivo);
        if ($texto === false) {
            throw new Exception('Não foi possível ler o arquivo');
        }
        
        // Detecta encoding e converte para UTF-8
        $encoding = mb_detect_encoding($texto, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $texto = mb_convert_encoding($texto, 'UTF-8', $encoding);
        }
        
        return $this->limparTexto($texto);
    }
    
    /**
     * Extrai texto de imagem usando OCR (Google Vision API via OpenAIService)
     */
    private function extrairTextoImagem($caminhoArquivo)
    {
        // Usa o serviço de OCR existente (Google Vision)
        require_once __DIR__ . '/OpenAIService.php';
        $openAIService = new \App\Services\OpenAIService();
        
        try {
            // Lê imagem como base64
            $imagemData = file_get_contents($caminhoArquivo);
            $base64 = base64_encode($imagemData);
            
            // Usa transcreverComGoogleVision (que já existe)
            $texto = $openAIService->transcreverComGoogleVision($base64);
            
            return $this->limparTexto($texto);
        } catch (Exception $e) {
            throw new Exception('Erro ao extrair texto da imagem: ' . $e->getMessage());
        }
    }
    
    /**
     * Divide texto em chunks para processamento
     * Usa estratégia de sobreposição (overlap) para manter contexto
     */
    public function dividirEmChunks($texto, $tamanhoChunk = 1000, $overlap = 200)
    {
        // Remove espaços excessivos e quebras de linha
        $texto = preg_replace('/\s+/', ' ', trim($texto));
        
        // Se o texto for menor que o chunk, retorna inteiro
        if (mb_strlen($texto) <= $tamanhoChunk) {
            return [[
                'texto' => $texto,
                'inicio' => 0,
                'fim' => mb_strlen($texto)
            ]];
        }
        
        $chunks = [];
        $posicao = 0;
        $tamanhoTexto = mb_strlen($texto);
        
        while ($posicao < $tamanhoTexto) {
            $fim = min($posicao + $tamanhoChunk, $tamanhoTexto);
            
            // Tenta quebrar em ponto final, vírgula ou espaço
            if ($fim < $tamanhoTexto) {
                // Procura último ponto final antes do fim
                $ultimoPonto = mb_strrpos(mb_substr($texto, $posicao, $fim - $posicao), '.');
                if ($ultimoPonto !== false && $ultimoPonto > $tamanhoChunk * 0.5) {
                    $fim = $posicao + $ultimoPonto + 1;
                } else {
                    // Procura último espaço
                    $ultimoEspaco = mb_strrpos(mb_substr($texto, $posicao, $fim - $posicao), ' ');
                    if ($ultimoEspaco !== false) {
                        $fim = $posicao + $ultimoEspaco;
                    }
                }
            }
            
            $chunkTexto = mb_substr($texto, $posicao, $fim - $posicao);
            
            if (mb_strlen(trim($chunkTexto)) > 0) {
                $chunks[] = [
                    'texto' => trim($chunkTexto),
                    'inicio' => $posicao,
                    'fim' => $fim
                ];
            }
            
            // Move posição com overlap
            $posicao = max($fim - $overlap, $posicao + 1);
        }
        
        return $chunks;
    }
    
    /**
     * Limpa e normaliza texto
     */
    private function limparTexto($texto)
    {
        // Remove caracteres de controle
        $texto = preg_replace('/[\x00-\x1F\x7F]/u', '', $texto);
        
        // Normaliza espaços
        $texto = preg_replace('/\s+/', ' ', $texto);
        
        // Remove espaços no início e fim
        $texto = trim($texto);
        
        return $texto;
    }
    
    /**
     * Verifica se comando existe no sistema
     */
    private function comandoExiste($comando)
    {
        $where = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        $result = shell_exec("$where $comando 2>&1");
        return !empty($result);
    }
}

