<?php

namespace App\Services;

use App\Exceptions\AccountSetupDeliveryException;
use App\Mail\EmailChangeOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailChangeOtpService
{
    public const EXPIRES_MINUTES = 15;

    public const SETTING = 'nutilize.email_change_code';

    public function issue(User $user, string $newEmail): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $purpose = $this->purpose((int) $user->user_id);

        DB::table('email_otps')->where('purpose', $purpose)->delete();
        DB::table('email_otps')->insert([
            'email' => $newEmail,
            'code' => hash('sha256', $code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'created_at' => now(),
        ]);

        $this->deliver($user, $newEmail, $code);
    }

    public function confirm(User $user, string $code): User
    {
        $purpose = $this->purpose((int) $user->user_id);
        $row = DB::table('email_otps')
            ->where('purpose', $purpose)
            ->where('code', hash('sha256', $code))
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired.',
            ]);
        }

        $newEmail = (string) $row->email;
        $taken = User::query()
            ->whereRaw('lower(email) = ?', [strtolower($newEmail)])
            ->where('user_id', '!=', $user->user_id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'code' => 'This email address is already in use.',
            ]);
        }

        DB::transaction(function () use ($user, $newEmail, $code, $purpose): void {
            DB::select('select set_config(?, ?, true)', [self::SETTING, $code]);
            $user->email = $newEmail;
            $user->save();
            DB::table('email_otps')->where('purpose', $purpose)->delete();
        });

        return $user->refresh();
    }

    private function purpose(int $userId): string
    {
        return 'email_change:'.$userId;
    }

    private function deliver(User $user, string $newEmail, string $code): void
    {
        if (config('mail.default') === 'log') {
            Log::info('Email change code was not delivered because the mailer is log.', [
                'user_id' => $user->user_id,
            ]);

            throw new AccountSetupDeliveryException('The verification email could not be sent because mail is not configured.');
        }

        try {
            Mail::to($newEmail)->send(new EmailChangeOtpMail($user, $code));
        } catch (\Throwable $exception) {
            Log::warning('Email change code could not be sent.', [
                'user_id' => $user->user_id,
                'exception' => $exception::class,
            ]);

            throw new AccountSetupDeliveryException('The verification email could not be sent.');
        }
    }
}
