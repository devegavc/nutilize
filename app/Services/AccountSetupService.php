<?php

namespace App\Services;

use App\Exceptions\AccountSetupDeliveryException;
use App\Mail\AccountSetupMail;
use App\Models\AccountSetupToken;
use App\Models\AccountSetupTokenRedemption;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AccountSetupService
{
    public const EXPIRES_HOURS = 72;

    public const DELIVERY_SENT = 'sent';

    public const DELIVERY_LOGGED = 'logged';

    public const TOKEN_INVALID = 'invalid';

    public const TOKEN_EXPIRED = 'expired';

    public const TOKEN_USED = 'used';

    private const SETUP_SETTING = 'nutilize.setup_token';

    public function unknownPassword(): string
    {
        return Str::password(48);
    }

    public function makeToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function issue(User $user): string
    {
        $token = $this->makeToken();

        AccountSetupToken::query()->updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'token_hash' => $this->hashToken($token),
                'expires_at' => now()->addHours(self::EXPIRES_HOURS),
                'created_at' => now(),
            ],
        );

        return $token;
    }

    public function deliver(User $user, string $token): string
    {
        if (config('mail.default') === 'log') {
            Log::info('Account setup email was not delivered to an inbox because the mailer is log. The setup link was omitted from this log.', [
                'user_id' => $user->user_id,
            ]);

            return self::DELIVERY_LOGGED;
        }

        try {
            Mail::to($user->email)->send(new AccountSetupMail($user, $token));
        } catch (\Throwable $exception) {
            Log::warning('Account setup email could not be sent.', [
                'user_id' => $user->user_id,
                'exception' => $exception::class,
            ]);

            throw new AccountSetupDeliveryException('The setup email could not be sent.');
        }

        return self::DELIVERY_SENT;
    }

    public function successMessage(string $delivery): string
    {
        if ($delivery === self::DELIVERY_LOGGED) {
            return 'User account created successfully. Password setup instructions were not delivered to an inbox because the mailer is set to log.';
        }

        return 'User account created successfully. Password setup instructions have been sent to the user\'s email address.';
    }

    public function findByRawToken(string $token): ?AccountSetupToken
    {
        if ($this->tokenFormatIsValid($token) !== true) {
            return null;
        }

        return AccountSetupToken::query()
            ->where('token_hash', $this->hashToken($token))
            ->first();
    }

    public function stateFor(string $token): string
    {
        if ($this->tokenFormatIsValid($token) !== true) {
            return self::TOKEN_INVALID;
        }

        $record = $this->findByRawToken($token);

        if ($record) {
            return $record->expires_at->lte(now()) ? self::TOKEN_EXPIRED : 'form';
        }

        if ($this->wasRedeemed($token)) {
            return self::TOKEN_USED;
        }

        return self::TOKEN_INVALID;
    }

    /**
     * @return array{state: string, token: string, username: ?string}
     */
    public function pageData(string $token): array
    {
        $state = $this->stateFor($token);
        $username = null;

        if ($state === 'form') {
            $record = $this->findByRawToken($token)?->loadMissing('user');
            $username = $record?->user?->username;
            if (!is_string($username) || $username === '') {
                $state = self::TOKEN_INVALID;
                $username = null;
            }
        }

        return [
            'state' => $state,
            'token' => $token,
            'username' => $username,
        ];
    }

    public function complete(string $token, string $password): void
    {
        $state = $this->stateFor($token);
        if ($state !== 'form') {
            throw new AccountSetupDeliveryException($state);
        }

        DB::disableQueryLog();

        try {
            DB::transaction(function () use ($token, $password): void {
                $record = AccountSetupToken::query()
                    ->where('token_hash', $this->hashToken($token))
                    ->lockForUpdate()
                    ->first();

                if (!$record || $record->expires_at->lte(now())) {
                    throw new AccountSetupDeliveryException($this->closedState($token, $record));
                }

                $user = User::query()
                    ->where('user_id', $record->user_id)
                    ->lockForUpdate()
                    ->first();

                if (!$user) {
                    throw new AccountSetupDeliveryException(self::TOKEN_INVALID);
                }

                DB::select('select set_config(?, ?, true)', [self::SETUP_SETTING, $token]);

                $authUserId = $this->syncMobileLogin($user, $password);

                $user->password = $password;
                $user->auth_user_id = $authUserId;
                $user->save();

                AccountSetupTokenRedemption::query()->create([
                    'token_hash' => $this->hashToken($token),
                    'consumed_at' => now(),
                ]);

                $record->delete();
            });
        } catch (AccountSetupDeliveryException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            Log::error('Password setup could not be saved.', [
                'sqlstate' => $exception->errorInfo[0] ?? $exception->getCode(),
            ]);

            throw new AccountSetupDeliveryException('save_failed');
        }
    }

    private function syncMobileLogin(User $user, string $password): string
    {
        $base = rtrim((string) config('services.supabase.url'), '/');
        $key = (string) config('services.supabase.service_role_key');
        $email = strtolower(trim((string) $user->email));

        if ($base === '' || $key === '' || $email === '') {
            throw new AccountSetupDeliveryException('save_failed');
        }

        $existing = DB::selectOne(
            'select id::text as id from auth.users where lower(email) = ? limit 1',
            [$email]
        );
        $existingId = is_object($existing) ? (string) ($existing->id ?? '') : '';

        $request = Http::withHeaders([
            'apikey' => $key,
            'Authorization' => 'Bearer '.$key,
        ])->acceptJson()->asJson()->timeout(20);

        if ($existingId !== '') {
            $response = $request->put($base.'/auth/v1/admin/users/'.$existingId, [
                'password' => $password,
                'email_confirm' => true,
            ]);
            $authUserId = $existingId;
        } else {
            $response = $request->post($base.'/auth/v1/admin/users', [
                'email' => $email,
                'password' => $password,
                'email_confirm' => true,
            ]);
            $authUserId = (string) $response->json('id');
        }

        if (!$response->successful() || $authUserId === '') {
            throw new AccountSetupDeliveryException('save_failed');
        }

        return $authUserId;
    }

    private function tokenFormatIsValid(string $token): bool
    {
        return preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) === 1;
    }

    private function wasRedeemed(string $token): bool
    {
        if ($this->tokenFormatIsValid($token) !== true) {
            return false;
        }

        return AccountSetupTokenRedemption::query()
            ->where('token_hash', $this->hashToken($token))
            ->exists();
    }

    private function closedState(string $token, ?AccountSetupToken $record): string
    {
        if ($record) {
            return self::TOKEN_EXPIRED;
        }

        return $this->wasRedeemed($token) ? self::TOKEN_USED : self::TOKEN_INVALID;
    }
}
