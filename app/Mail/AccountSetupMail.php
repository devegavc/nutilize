<?php

namespace App\Mail;

use App\Models\User;
use App\Services\AccountSetupService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use SensitiveParameter;

class AccountSetupMail extends Mailable
{
    public function __construct(
        public User $user,
        #[SensitiveParameter]
        public string $setupToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Set up your NUtilize password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-setup',
            with: [
                'recipientName' => $this->user->displayName(),
                'username' => $this->user->username,
                'setupUrl' => rtrim((string) config('app.url'), '/').'/account/setup/'.$this->setupToken,
                'expiresHours' => AccountSetupService::EXPIRES_HOURS,
            ],
        );
    }
}
