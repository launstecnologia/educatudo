<?php

namespace App\Painel\Master\Services;

use App\Core\Database;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Segundo fator do painel master: código de 6 dígitos enviado por e-mail.
 * Sai pelo relay do Google Workspace (smtp-relay.gmail.com), o mesmo caminho
 * dos outros e-mails da plataforma. MAIL_HOST no .env troca esse destino.
 * O código em claro não fica na sessão nem no banco.
 */
class CodigoAcessoMasterService
{
    public const VALIDADE_MINUTOS = 10;
    public const MAX_TENTATIVAS = 5;
    public const REENVIO_SEGUNDOS = 60;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function emailConfigurado(): bool
    {
        return $this->smtpHost() !== '';
    }

    public function tabelaPronta(): bool
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS total
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabela',
            ['tabela' => 'usuarios_master_codigos_acesso']
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * Gera e envia um código, ou reaproveita o último se ainda estiver no intervalo de reenvio.
     *
     * @return array{id: int, email_mascarado: string, reaproveitado: bool}
     */
    public function emitir(int $usuarioId, string $email, string $nome): array
    {
        if (!$this->emailConfigurado()) {
            throw new \RuntimeException('O envio de e-mail do painel master não está configurado.');
        }
        if (!$this->tabelaPronta()) {
            throw new \RuntimeException(
                'O segundo fator ainda não está instalado. Aplique a migration 2026_11_19_usuarios_master_codigos_acesso_master.sql no banco master.'
            );
        }

        $mascara = $this->mascararEmail($email);
        $recente = $this->db->fetch(
            'SELECT id, TIMESTAMPDIFF(SECOND, criado_em, NOW()) AS segundos
             FROM usuarios_master_codigos_acesso
             WHERE usuario_id = :usuario_id
               AND usado_em IS NULL
               AND expira_em >= NOW()
             ORDER BY id DESC
             LIMIT 1',
            ['usuario_id' => $usuarioId]
        );
        if ($recente && (int) $recente['segundos'] < self::REENVIO_SEGUNDOS) {
            return [
                'id' => (int) $recente['id'],
                'email_mascarado' => $mascara,
                'reaproveitado' => true,
            ];
        }

        $this->db->query(
            'UPDATE usuarios_master_codigos_acesso
             SET usado_em = NOW()
             WHERE usuario_id = :usuario_id AND usado_em IS NULL',
            ['usuario_id' => $usuarioId]
        );

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($codigo, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new \RuntimeException('Não foi possível gerar o código de acesso. Tente novamente.');
        }

        $minutos = self::VALIDADE_MINUTOS;
        $id = (int) $this->db->insert(
            'INSERT INTO usuarios_master_codigos_acesso (usuario_id, codigo_hash, expira_em)
             VALUES (:usuario_id, :codigo_hash, DATE_ADD(NOW(), INTERVAL ' . $minutos . ' MINUTE))',
            [
                'usuario_id' => $usuarioId,
                'codigo_hash' => $hash,
            ]
        );

        try {
            $this->enviarCodigo($email, $nome, $codigo);
        } catch (\Throwable $e) {
            $this->db->query(
                'DELETE FROM usuarios_master_codigos_acesso WHERE id = :id',
                ['id' => $id]
            );
            error_log('[master_2fa] falha no envio: ' . $e->getMessage());
            throw new \RuntimeException('Não foi possível enviar o código de acesso. Tente novamente.');
        }

        try {
            $this->db->query(
                'DELETE FROM usuarios_master_codigos_acesso
                 WHERE usuario_id = :usuario_id
                   AND id < :id_atual
                   AND criado_em < DATE_SUB(NOW(), INTERVAL 1 DAY)',
                [
                    'usuario_id' => $usuarioId,
                    'id_atual' => $id,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[master_2fa] limpeza de códigos antigos falhou');
        }

        return [
            'id' => $id,
            'email_mascarado' => $mascara,
            'reaproveitado' => false,
        ];
    }

    /**
     * @return 'ok'|'invalido'|'esgotado'
     */
    public function confirmar(int $usuarioId, int $codigoId, string $codigoInformado): string
    {
        $digitos = preg_replace('/\D+/', '', $codigoInformado) ?? '';
        if (strlen($digitos) !== 6 || $usuarioId <= 0 || $codigoId <= 0) {
            return 'invalido';
        }
        if (!$this->tabelaPronta()) {
            return 'invalido';
        }

        $row = $this->db->fetch(
            'SELECT id, codigo_hash, tentativas
             FROM usuarios_master_codigos_acesso
             WHERE id = :id
               AND usuario_id = :usuario_id
               AND usado_em IS NULL
               AND expira_em >= NOW()
             LIMIT 1',
            [
                'id' => $codigoId,
                'usuario_id' => $usuarioId,
            ]
        );
        if (!$row) {
            return 'invalido';
        }
        if ((int) $row['tentativas'] >= self::MAX_TENTATIVAS) {
            return 'esgotado';
        }

        if (!password_verify($digitos, (string) $row['codigo_hash'])) {
            $this->db->query(
                'UPDATE usuarios_master_codigos_acesso
                 SET tentativas = tentativas + 1
                 WHERE id = :id AND usado_em IS NULL',
                ['id' => $codigoId]
            );
            $atual = $this->db->fetch(
                'SELECT tentativas FROM usuarios_master_codigos_acesso WHERE id = :id',
                ['id' => $codigoId]
            );
            if ($atual && (int) $atual['tentativas'] >= self::MAX_TENTATIVAS) {
                return 'esgotado';
            }

            return 'invalido';
        }

        $marcados = $this->db->update(
            'UPDATE usuarios_master_codigos_acesso
             SET usado_em = NOW()
             WHERE id = :id
               AND usado_em IS NULL
               AND expira_em >= NOW()
               AND tentativas < :max_tentativas',
            [
                'id' => $codigoId,
                'max_tentativas' => self::MAX_TENTATIVAS,
            ]
        );

        return $marcados === 1 ? 'ok' : 'invalido';
    }

    public function mascararEmail(string $email): string
    {
        $email = trim($email);
        $partes = explode('@', $email, 2);
        if (count($partes) !== 2 || $partes[0] === '' || $partes[1] === '') {
            return 'seu e-mail';
        }
        $visivel = function_exists('mb_substr') ? mb_substr($partes[0], 0, 1) : substr($partes[0], 0, 1);

        return $visivel . '***@' . $partes[1];
    }

    private function enviarCodigo(string $email, string $nome, string $codigo): void
    {
        if (!class_exists(PHPMailer::class)) {
            $autoload = __DIR__ . '/../../../../vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }
        }
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('PHPMailer indisponível.');
        }

        $host = $this->smtpHost();
        $porta = (int) $this->envMail('MAIL_PORT', '587');
        if ($porta <= 0) {
            $porta = 587;
        }
        $usuario = $this->envMail('MAIL_USERNAME', '');
        $senha = $this->envMail('MAIL_PASSWORD', '');
        $de = $this->envMail('MAIL_FROM_ADDRESS', 'noreply@educatudo.com');
        if ($de === '') {
            $de = 'noreply@educatudo.com';
        }
        $deNome = $this->envMail('MAIL_FROM_NAME', 'EducaTudo');
        if ($deNome === '') {
            $deNome = 'EducaTudo';
        }
        $destinoNome = trim($nome) !== '' ? trim($nome) : 'Admin';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $porta;
        $mail->Timeout = 20;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPAuth = $usuario !== '' && $senha !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = $usuario;
            $mail->Password = $senha;
        }
        if ($porta === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->setFrom($de, $deNome);
        $mail->addAddress($email, $destinoNome);
        $mail->isHTML(true);
        $mail->Subject = 'Código de acesso do painel master';
        $mail->Body = $this->corpoHtml($destinoNome, $codigo);
        $mail->AltBody = $destinoNome . ", seu código de acesso ao painel master é {$codigo}. "
            . 'Ele vale por ' . self::VALIDADE_MINUTOS . ' minutos. '
            . 'Se você não tentou entrar, ignore este e-mail e troque a senha.';
        $mail->send();
    }

    private function corpoHtml(string $nome, string $codigo): string
    {
        $nomeSeguro = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $codigoSeguro = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
        $minutos = self::VALIDADE_MINUTOS;

        return '<div style="font-family: sans-serif; color: #0f172a; line-height: 1.5;">'
            . '<p>Olá, ' . $nomeSeguro . '.</p>'
            . '<p>Use este código para entrar no painel master da EducaTudo:</p>'
            . '<p style="font-size: 28px; font-weight: 700; letter-spacing: 6px;">' . $codigoSeguro . '</p>'
            . '<p>Ele vale por ' . $minutos . ' minutos e só pode ser usado uma vez.</p>'
            . '<p>Se você não tentou entrar, ignore este e-mail e troque a senha.</p>'
            . '</div>';
    }

    private function smtpHost(): string
    {
        $host = $this->envMail('MAIL_HOST', '');

        return $host !== '' ? $host : 'smtp-relay.gmail.com';
    }

    private function envMail(string $chave, string $padrao): string
    {
        $valor = function_exists('env') ? env($chave, $padrao) : $padrao;
        $valor = trim((string) $valor);
        if (strlen($valor) >= 2) {
            $abre = $valor[0];
            $fecha = substr($valor, -1);
            if (($abre === '"' && $fecha === '"') || ($abre === "'" && $fecha === "'")) {
                $valor = trim(substr($valor, 1, -1));
            }
        }

        return $valor;
    }
}
