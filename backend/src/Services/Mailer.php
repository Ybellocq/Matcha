<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use function App\env;

final class Mailer
{
    public function send(string $to, string $subject, string $html): void
    {
        $mode = env('MAIL_MODE', 'log');

        if ($mode === 'log') {
            $logPath = __DIR__ . '/../../storage/logs/mail.log';
            $entry = sprintf("[%s] TO:%s SUBJECT:%s\n%s\n\n", date('c'), $to, $subject, $html);
            file_put_contents($logPath, $entry, FILE_APPEND);
            return;
        }

        if ($mode !== 'smtp') {
            throw new \RuntimeException('MAIL_MODE must be "smtp" or "log".');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) env('MAIL_HOST');
        $mail->Port = (int) env('MAIL_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = (string) env('MAIL_USER');
        $mail->Password = (string) env('MAIL_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $from = (string) env('MAIL_FROM', 'no-reply@example.com');
        $fromName = (string) env('MAIL_FROM_NAME', 'Matcha');

        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->send();
    }
}
