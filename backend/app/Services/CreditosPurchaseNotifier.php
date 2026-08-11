<?php
/**
 * Notificação simples após pagamento confirmado (e-mail opcional + log).
 */
namespace App\Services;

class CreditosPurchaseNotifier
{
    public static function notifyPaid(string $email, string $nome, float $creditos, string $escolaNome = ''): void
    {
        $subject = 'Créditos creditados — EducaTudo';
        $body = "Olá " . $nome . ",\n\n";
        $body .= 'Seus ' . \CreditosDecimalHelper::formatDisplay($creditos) . " créditos foram adicionados à sua carteira.\n";
        if ($escolaNome !== '') {
            $body .= 'Escola: ' . $escolaNome . "\n";
        }
        $body .= "\nObrigado.";

        error_log('[creditos_pago] email=' . $email . ' creditos=' . $creditos);

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
            @mail($email, $subject, $body, $headers);
        }
    }
}
