<?php
/**
 * EducaTudo - Serviço de E-mail
 * Gerencia envio de e-mails usando PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $db;
    private $config;
    
    public function __construct()
    {
        // Garantir que o autoload do Composer está carregado
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
            }
        }
        
        $this->db = Database::getInstance();
        $this->loadEmailConfig();
    }
    
    /**
     * Carrega configurações de e-mail do banco de dados
     */
    private function loadEmailConfig()
    {
        // Buscar configurações do banco
        $configs = $this->db->fetchAll(
            "SELECT config_key, config_value FROM config_layout WHERE config_key LIKE 'email_%'"
        );
        
        // Configurações padrão (serão sobrescritas pelo banco se existirem)
        $this->config = [
            'smtp_host' => 'smtp-relay.gmail.com',
            'smtp_port' => '587',
            'smtp_secure' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'from_email' => 'noreply@educatudo.com',
            'from_name' => 'EducaTudo',
            'reply_to' => null
        ];
        
        // Sobrescrever com valores do banco (prioridade máxima)
        foreach ($configs as $config) {
            $key = str_replace('email_', '', $config['config_key']);
            // Não sobrescrever se o valor do banco for vazio (exceto para username/password que podem ser vazios intencionalmente)
            if (!empty($config['config_value']) || in_array($key, ['smtp_username', 'smtp_password', 'reply_to'])) {
                $this->config[$key] = $config['config_value'];
            }
        }
        
        // Fallback para .env se não houver no banco
        if (empty($this->config['smtp_host'])) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $env = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || strpos($line, '#') === 0) continue;
                    if (strpos($line, '=') === false) continue;
                    list($name, $value) = explode('=', $line, 2);
                    $env[trim($name)] = trim($value);
                }
                $this->config['smtp_host'] = $env['SMTP_HOST'] ?? null;
                $this->config['smtp_port'] = $env['SMTP_PORT'] ?? 587;
                $this->config['smtp_secure'] = $env['SMTP_SECURE'] ?? 'tls';
                $this->config['smtp_username'] = $env['SMTP_USERNAME'] ?? null;
                $this->config['smtp_password'] = $env['SMTP_PASSWORD'] ?? null;
                $this->config['from_email'] = $env['SMTP_FROM_EMAIL'] ?? null;
                $this->config['from_name'] = $env['SMTP_FROM_NAME'] ?? 'EducaTudo';
                $this->config['reply_to'] = $env['SMTP_REPLY_TO'] ?? null;
            }
        }
    }
    
    /**
     * Verifica se o e-mail está configurado
     */
    public function isConfigured()
    {
        // Precisa do host (credenciais são opcionais para alguns servidores SMTP)
        return !empty($this->config['smtp_host']);
    }
    
    /**
     * Envia e-mail
     */
    public function send($to, $subject, $body, $isHTML = true, $attachments = [])
    {
        // Carregar autoloader do Composer se não estiver carregado
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
                require_once __DIR__ . '/../../vendor/autoload.php';
            } else {
                throw new Exception('PHPMailer não encontrado. Execute: composer install');
            }
        }
        
        $mail = new PHPMailer(true);
        
        try {
            // Configurações do servidor
            $mail->isSMTP();
            
            // Habilitar debug em desenvolvimento
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                $mail->SMTPDebug = 2; // Mostrar mensagens de debug
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer Debug (Level $level): $str");
                };
            }
            
            // Configurações do servidor SMTP
            $mail->Host = $this->config['smtp_host'] ?? 'smtp-relay.gmail.com';
            $mail->Port = intval($this->config['smtp_port'] ?? 587);
            
            // Configurações de timeout e keepalive (aumentado para conexões lentas)
            $mail->Timeout = 60; // Aumentado de 30 para 60 segundos
            $mail->SMTPKeepAlive = false;
            
            // Configurações adicionais para melhorar conexão
            $mail->SMTPAutoTLS = true;
            $mail->SMTPAuth = false; // Será definido abaixo se houver credenciais
            
            // Configurar autenticação SMTP
            if (!empty($this->config['smtp_username']) && !empty($this->config['smtp_password'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->config['smtp_username'];
                $mail->Password = $this->config['smtp_password'];
            } else {
                // Tentar sem autenticação se não houver credenciais
                $mail->SMTPAuth = false;
            }
            
            // Configurar segurança
            $smtpSecure = strtolower($this->config['smtp_secure'] ?? 'tls');
            if ($smtpSecure === 'tls' || $smtpSecure === 'starttls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($smtpSecure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                // Se não especificado, tentar sem criptografia primeiro
                $mail->SMTPSecure = '';
            }
            
            $mail->CharSet = 'UTF-8';
            
            // Opções SSL/TLS mais flexíveis para evitar problemas de conexão
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                ]
            ];
            
            // Remetente
            $fromEmail = $this->config['from_email'] ?? 'noreply@climail.com.br';
            $fromName = $this->config['from_name'] ?? 'EducaTudo';
            $mail->setFrom($fromEmail, $fromName);
            
            if (!empty($this->config['reply_to'])) {
                $mail->addReplyTo($this->config['reply_to']);
            }
            
            // Destinatário
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        $mail->addAddress($name);
                    } else {
                        $mail->addAddress($email, $name);
                    }
                }
            } else {
                $mail->addAddress($to);
            }
            
            // Conteúdo
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            if (!$isHTML) {
                $mail->AltBody = strip_tags($body);
            }
            
            // Anexos
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } else {
                    $mail->addAttachment($attachment);
                }
            }
            
            // Enviar
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            // Logar erro detalhado no app.data.log
            require_once __DIR__ . '/../Core/Logger.php';
            Logger::error(
                "Erro ao enviar e-mail via SMTP",
                [
                    'exception' => $e,
                    'phpmailer_error' => $mail->ErrorInfo ?? 'N/A',
                    'to' => $to ?? 'N/A',
                    'subject' => $subject ?? 'N/A',
                    'smtp_host' => $this->config['smtp_host'] ?? 'N/A',
                    'smtp_port' => $this->config['smtp_port'] ?? 'N/A',
                    'smtp_secure' => $this->config['smtp_secure'] ?? 'N/A',
                    'smtp_username' => !empty($this->config['smtp_username']) ? '[CONFIGURADO]' : '[VAZIO]',
                    'smtp_auth' => isset($this->config['smtp_username']) && !empty($this->config['smtp_username']) ? 'SIM' : 'NÃO',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ],
                'email'
            );
            throw new Exception("Erro ao enviar e-mail: " . $mail->ErrorInfo);
        }
    }
    
    /**
     * Envia e-mail de recuperação de senha
     */
    public function sendPasswordReset($to, $name, $resetToken, $resetUrl)
    {
        $subject = 'Recuperação de Senha - EducaTudo';
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>EducaTudo</h1>
                    <p>Recuperação de Senha</p>
                </div>
                <div class='content'>
                    <p>Olá, <strong>{$name}</strong>!</p>
                    <p>Você solicitou a recuperação de senha. Clique no botão abaixo para redefinir sua senha:</p>
                    <p style='text-align: center;'>
                        <a href='{$resetUrl}' class='button'>Redefinir Senha</a>
                    </p>
                    <p>Ou copie e cole este link no seu navegador:</p>
                    <p style='word-break: break-all; background: #fff; padding: 10px; border-radius: 5px;'>{$resetUrl}</p>
                    <p><strong>Este link expira em 1 hora.</strong></p>
                    <p>Se você não solicitou esta recuperação, ignore este e-mail.</p>
                </div>
                <div class='footer'>
                    <p>Este é um e-mail automático, por favor não responda.</p>
                    <p>&copy; " . date('Y') . " EducaTudo. Todos os direitos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $body, true);
    }
}
