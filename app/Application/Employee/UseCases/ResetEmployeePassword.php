<?php

declare(strict_types=1);

namespace App\Application\Employee\UseCases;

use App\Application\Audit\Audit;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ResetEmployeePassword
{
    public function __construct(
        private AssignmentRepositoryInterface $assignments,
    ) {}

    /** @return string password baru, ditampilkan sekali ke HR */
    public function __invoke(Company $company, User $user, ?string $password): string
    {
        if ($this->assignments->paginateForCompany($company, 1, $user->id, false)->first() === null) {
            abort(404);
        }

        $plain = $password ?? Str::random(10);

        $user->forceFill(['password' => Hash::make($plain)])->save();
        $user->tokens()->delete();

        Audit::record($company, null, 'karyawan.reset_password', 'user', $user->id);

        return $plain;
    }
}
