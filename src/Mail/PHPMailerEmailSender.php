<?php

declare(strict_types=1);
namespace UserBase\Mail;

use RuntimeException;
use UserBase\Config\Configuration;
use UserBase\Service\IEmailSender;
use PHPMailer\PHPMailer\PHPMailer;

public final class PHPMailerEmailSender implements IEmailSender {

    public function __construct
    ( private readonly Configuration $config )
    {
        if (!$this->config->IsEmailConfigured())
        { throw new RuntimeException('E-Mail Sending is not configured'); }
    }


    public function SendVerificationEmail(
        string $recipientEmail,
        string $recipientUsername,
        string $verificationURL
    ): void {

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $this->config->smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $this->config->smtpUsername;
        $mail->Password = $this->config->smtpPassword;

        // Use implicit TLS on port 465;  STARTTLS otherwise
        $mail->SMTPSecure(
            $this->config->smtpPort === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS
        );

        $mail->Port = $this->config->smtpPort;

        $mail->setFrom(
            $this->config->mailFromAddress,
            $this->config->mailFromName
        );

        $mail->addAddress(
            $recipientEmail,
            $recipientUsername
        );

        $mail->isHTML(true);
        $mail->Subject = 'Verify your E-Mail Address';

        $safeUsername = htmlspecialchars(
            $recipientUsername,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $safeURL = htmlspecialchars(
            $verificationURL,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $mail->Body = "
            <p>Hello {$safeUsername},</p>
            <p>Please verify your email address by clicking the link below:</p>
            <p><a href=\"{$safeUrl}\">Verify email address</a></p>
            <p>This link expires in 24 hours. If you did not create this account,
            you can ignore this email.</p>
        ";

        $mail->AltBody =
            "Hello {$recipientUsername},\n\n" .
            "Please verify your email address using this link:\n" .
            "{$verificationUrl}\n\n" .
            "This link expires in 24 hours. If you did not create this account, " .
            "you can ignore this email.";

        $mail->send();
    }

}

?>