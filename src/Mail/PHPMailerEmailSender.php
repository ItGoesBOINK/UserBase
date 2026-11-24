<?php

declare(strict_types=1);
namespace UserBase\Mail;

use RuntimeException;
use PHPMailer\PHPMailer\PHPMailer;
use UserBase\Exception\EmailDeliveryException;
use UserBase\Config\Configuration;
use UserBase\Config\EmailServiceConfiguration;
use UserBase\Mail\IEmailSender;

final class PHPMailerEmailSender implements IEmailSender {

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

        $mail = $this->BuildMailer(
            $this->config->accountVerificationEmailConfig,
            $recipientEmail,
            $recipientUsername,
            'Verify your E-Mail Address'
        );

        $safeUsername = $this->GetSafeString($recipientUsername);
        $safeURL = $this->GetSafeString($verificationURL);

        $mail->Body = "
            <p>Hello {$safeUsername},</p>
            <p>Please verify your email address by clicking the link below:</p>
            <p><a href=\"{$safeURL}\">Verify email address</a></p>
            <p>This link expires in 24 hours. If you did not create this account,
            you can ignore this email.</p>
        ";

        $mail->AltBody =
            "Hello {$recipientUsername},\n\n" .
            "Please verify your email address using this link:\n" .
            "{$verificationURL}\n\n" .
            "This link expires in 24 hours. If you did not create this account, " .
            "you can ignore this email.";

        try {
            $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            throw new EmailDeliveryException(
                'Failed to send verification E-Mail',
                0,
                $e
            );
        }
    }



    public function SendPasswordResetEmail(
        string $recipientEmail,
        string $recipientUsername,
        string $resetURL
    ): void {

        $mail = $this->BuildMailer(
            $this->config->passwordResetEmailConfig,
            $recipientEmail,
            $recipientUsername,
            'Reset Your Password'
        );

        $safeUsername = $this->GetSafeString($recipientUsername);
        $safeURL = $this->GetSafeString($resetURL);

        $mail->Body = "
            <p>Hello {$safeUsername},</p>
            <p>A password reset was requested for your account.</p>
            <p>
                <a href=\"{$safeURL}\">Reset your password</a>
            </p>
            <p>
                This link is valid for a limited time and can only be used once.
                If you did not request a password reset, you can safely ignore
                this email.
            </p>
        ";

        $mail->AltBody =
            "Hello {$recipientUsername},\n\n" .
            "A password reset was requested for your account.\n\n" .
            "Use this link to reset your password:\n" .
            "{$resetURL}\n\n" .
            "This link is valid for a limited time and can only be used once. " .
            "If you did not request a password reset, you can safely ignore " .
            "this email.";

        try {
            $mail->Send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            throw new EmailDeliveryException(
                'Failed to send password reset email',
                0, $e
            );
        }
    }

    private function GetSafeString(string $str): string
    {
        return htmlspecialchars(
           $str,
           ENT_QUOTES | ENT_SUBSTITUTE,
           'UTF-8'
        );
    }

    private function BuildMailer(
        EmailServiceConfiguration $emailConfig,
        string $recipientEmail,
        string $recipientUsername,
        string $subject
    ): PHPMailer {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $emailConfig->smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig->smtpUsername;
        $mail->Password = $emailConfig->smtpPassword;

        // Use implicit TLS on port 465;  STARTTLS otherwise
        $mail->SMTPSecure =
            $emailConfig->smtpPort === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = $emailConfig->smtpPort;

        $mail->setFrom(
            $emailConfig->senderEmailAddress,
            $emailConfig->senderName
        );

        $mail->addAddress(
            $recipientEmail,
            $recipientUsername
        );

        $mail->isHTML(true);
        $mail->Subject = $subject;

        return $mail;
    }

}

?>