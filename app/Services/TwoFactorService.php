<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa)
    {
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function encryptSecret(string $plainSecret): string
    {
        return Crypt::encryptString($plainSecret);
    }

    public function decryptSecret(?string $encryptedSecret): ?string
    {
        if (! $encryptedSecret) {
            return null;
        }

        try {
            return Crypt::decryptString($encryptedSecret);
        } catch (Throwable) {
            return null;
        }
    }

    public function getProvisioningUri(User $user, string $plainSecret): string
    {
        $issuer = config('app.name', 'Veshop');

        return $this->google2fa->getQRCodeUrl($issuer, $user->email, $plainSecret);
    }

    public function maskSecret(string $plainSecret): string
    {
        return trim(chunk_split($plainSecret, 4, ' '));
    }

    public function verifyCode(string $plainSecret, ?string $oneTimeCode): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', (string) $oneTimeCode) ?? '';

        if (strlen($normalizedCode) !== 6) {
            return false;
        }

        return $this->google2fa->verifyKey($plainSecret, $normalizedCode, 1);
    }
}
