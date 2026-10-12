<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Titik tunggal cek izin. Scope cabang menyusul di slice berikutnya. */
final class Access
{
    public static function require(User $user, Company $company, string $permission): void
    {
        setPermissionsTeamId($company->id);

        if (! $user->hasPermissionTo($permission)) {
            throw new HttpException(403, 'Akses ditolak.');
        }
    }
}
