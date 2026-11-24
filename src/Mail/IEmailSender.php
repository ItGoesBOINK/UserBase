<?php

declare(strict_types=1);
namespace UserBase\Mail;

interface IEmailSender {

    public function SendVerificationEmail(
        string $recipientEmail,
        string $recipientUsername,
        string $verificationURL
    ): void;

    public function SendPasswordResetEmail(
        string $recipientEmail,
        string $recipientUsername,
        string $resetURL
    ): void;

}


?>