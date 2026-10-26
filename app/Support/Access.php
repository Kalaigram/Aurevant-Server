<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Titik tunggal cek izin + scope cabang. */
final class Access
{
    public static function require(User $user, Company $company, string $permission): void
    {
        setPermissionsTeamId($company->id);

        if (! $user->hasPermissionTo($permission)) {
            throw new HttpException(403, 'Akses ditolak.');
        }
    }

    /**
     * Daftar id cabang yang boleh diakses, atau null bila seluruh perusahaan.
     * Tanpa penempatan aktif = daftar kosong (fail-closed).
     *
     * @return list<int>|null
     */
    public static function scopeBranches(User $user, Company $company): ?array
    {
        $assignments = $user->assignments()
            ->where('company_id', $company->id)
            ->whereNull('ended_at')
            ->pluck('branch_id')
            ->all();

        if ($assignments === []) {
            return [];
        }

        if (in_array(null, $assignments, true)) {
            return null;
        }

        return array_values(array_unique(array_map('intval', $assignments)));
    }

    public static function hasActiveAssignment(User $user, Company $company): bool
    {
        return $user->assignments()
            ->where('company_id', $company->id)
            ->whereNull('ended_at')
            ->exists();
    }

    /** @param list<int> $branchIds */
    public static function requireBranches(User $user, Company $company, array $branchIds, bool $any = false): void
    {
        $scope = self::scopeBranches($user, $company);

        if ($scope === null) {
            return;
        }

        $covered = array_intersect($branchIds, $scope);
        $ok = $any ? $covered !== [] : count($covered) === count($branchIds);

        if (! $ok) {
            throw new HttpException(403, 'Akses ditolak.');
        }
    }

    public static function requireBranch(User $user, Company $company, int $branchId): void
    {
        self::requireBranches($user, $company, [$branchId]);
    }
}
