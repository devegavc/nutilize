<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailChangeOtpService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use SensitiveParameter;

class EmailChangeOtpMail extends Mailable
{
    public function __construct(
        public User $user,
        #[SensitiveParameter]
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your new NUtilize email',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-change-otp',
            with: [
                'recipientName' => $this->user->displayName(),
                'code' => $this->code,
                'expiresMinutes' => EmailChangeOtpService::EXPIRES_MINUTES,
            ],
        );
    }
}
