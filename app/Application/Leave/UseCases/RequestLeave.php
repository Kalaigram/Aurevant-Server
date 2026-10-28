<?php

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Hr\UseCases\ManageLeaveBalances;
use App\Application\Leave\DTOs\LeaveDTO;
use App\Application\Notify\Notifier;
use App\Domain\Leave\Repositories\LeaveRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use App\Support\Access;
use Illuminate\Validation\ValidationException;

final class RequestLeave
{
    public function __construct(
        private LeaveRepositoryInterface $leaves,
        private ManageLeaveBalances $balances,
    ) {}

    public function __invoke(Company $company, User $user, string $type, string $from, string $to, string $reason): LeaveDTO
    {
        if (! Access::hasActiveAssignment($user, $company)) {
            abort(404);
        }

        if ($type === 'cuti') {
            $days = ManageLeaveBalances::inclusiveDays($from, $to);
            $available = $this->balances->available($company, $user, (int) date('Y', strtotime($from)));

            if ($days > $available) {
                throw ValidationException::withMessages([
                    'saldo_cuti' => 'Saldo cuti tidak mencukupi (tersedia '
                        .number_format($available, 2, ',', '.').' hari, diajukan '.$days.' hari).',
                ]);
            }
        }

        $leave = $this->leaves->create($company, $user, [
            'type' => $type,
            'from_date' => $from,
            'to_date' => $to,
            'reason' => $reason,
            'status' => 'diajukan',
        ]);

        Notifier::toPermissioned(
            $company,
            ['absensi.approve', 'karyawan.kelola'],
            'cuti.diajukan',
            'Pengajuan '.$type.' '.$user->name.' menunggu persetujuan.',
            $from.' s/d '.$to,
            '/leaves',
            [$user->id],
        );

        return LeaveDTO::fromModel($leave);
    }
}
