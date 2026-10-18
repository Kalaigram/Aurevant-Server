<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use Illuminate\Support\Facades\Password;

/**
 * Kirim tautan atur ulang. Hasil selalu "sukses" di mata pemanggil
 * agar keberadaan sebuah email tidak bocor.
 */
final class RequestPasswordReset
{
    public function __invoke(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }
}
