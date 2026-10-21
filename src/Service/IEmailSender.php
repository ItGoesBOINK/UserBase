<?php

declare(strict_types=1);
namespace UserBase\Service;

interface IEmailSender {

    public function SendVerificationEmail(
        string $recipientEmail,
        string $recipientUsername,
        string $verificationURL
    ): void;

}


?>