<?php

declare(strict_types=1);

namespace UserBase\Tests;

use UserBase\Mail\IEmailSender;
use UserBase\Exception\EmailDeliveryException;

final class TestEmailSender implements IEmailSender
{
    public array $sentEmails = [];
    public bool $shouldFail = false;

    public function SendVerificationEmail(
        string $recipientEmail,
        string $recipientUsername,
        string $verificationURL
    ): void {

        $this->CheckShouldSimulateFailure();

        $this->SendConfirmationEmail(
            'account_verification',
            $recipientEmail,
            $recipientUsername,
            $verificationURL
        );
    }

    public function SentEmailCount(): int { return count($this->sentEmails); }

    public function SendPasswordResetEmail(
        string $recipientEmail,
        string $recipientUsername,
        string $resetURL
    ): void {

        $this->CheckShouldSimulateFailure();

        $this->SendConfirmationEmail(
            'password_reset',
            $recipientEmail,
            $recipientUsername,
            $resetURL
        );
    }

    private function SendConfirmationEmail(
        string $actionType,
        string $recipientEmail,
        string $recipientUsername,
        string $confirmationURL
    ): void {
        $this->sentEmails[] = [
            'type' => $actionType,
            'email' => $recipientEmail,
            'username' => $recipientUsername,
            'url' => $confirmationURL
        ];
    }

    private function CheckShouldSimulateFailure(): void
    {
        if ($this->shouldFail)
        { throw new EmailDeliveryException('>>>     Simulated E-Mail delivery failure [Success]'); }
    }
}

?>