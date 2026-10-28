<?php

declare(strict_types=1);

namespace App\Application\Hr\UseCases;

use App\Application\Hr\DTOs\LeaveBalanceDTO;
use App\Models\Company;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageLeaveBalances
{
    public const DEFAULT_ENTITLEMENT = 12.0;

    public function canManage(User $actor): bool
    {
        return $actor->is_super_admin
            || $actor->can('absensi.approve')
            || $actor->can('karyawan.kelola');
    }

    /** @return list<LeaveBalanceDTO> */
    public function list(Company $company, User $actor, ?int $userId, int $year): array
    {
        if (! $this->canManage($actor)) {
            if ($userId !== null && $userId !== $actor->id) {
                abort(403, 'Akses ditolak.');
            }

            $userId = $actor->id;
        }

        if ($userId !== null) {
            $target = User::query()->whereKey($userId)->first();
            if ($target === null || ! Access::hasActiveAssignment($target, $company)) {
                abort(404);
            }
            $users = collect([$target]);
        } else {
            $users = User::query()
                ->whereHas('assignments', fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->whereNull('ended_at'))
                ->orderBy('name')
                ->get();
        }

        $balances = LeaveBalance::query()
            ->where('company_id', $company->id)
            ->where('year', $year)
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        return $users->map(function (User $user) use ($balances, $year): LeaveBalanceDTO {
            $balance = $balances->get($user->id);
            if ($balance === null) {
                return LeaveBalanceDTO::empty($user, $year, self::DEFAULT_ENTITLEMENT);
            }

            $balance->setRelation('user', $user);

            return LeaveBalanceDTO::fromModel($balance);
        })->values()->all();
    }

    public function upsert(Company $company, User $target, int $year, float $entitlement, float $carryover): LeaveBalanceDTO
    {
        if (! Access::hasActiveAssignment($target, $company)) {
            abort(404);
        }

        $balance = LeaveBalance::query()->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $target->id, 'year' => $year],
            ['entitlement' => $entitlement, 'carryover' => $carryover],
        );
        $balance->setRelation('user', $target);

        return LeaveBalanceDTO::fromModel($balance);
    }

    public function available(Company $company, User $user, int $year): float
    {
        $balance = LeaveBalance::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        return $balance?->available() ?? self::DEFAULT_ENTITLEMENT;
    }

    public function consume(Company $company, User $user, int $year, float $days): LeaveBalance
    {
        return DB::transaction(function () use ($company, $user, $year, $days): LeaveBalance {
            $balance = LeaveBalance::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                $balance = LeaveBalance::query()->create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'year' => $year,
                    'entitlement' => self::DEFAULT_ENTITLEMENT,
                    'carryover' => 0,
                    'used' => 0,
                ]);
            }

            if ($balance->available() + 0.001 < $days) {
                throw ValidationException::withMessages([
                    'saldo_cuti' => 'Saldo cuti tidak mencukupi (tersedia '
                        .number_format($balance->available(), 2, ',', '.').' hari).',
                ]);
            }

            $balance->used = (float) $balance->used + $days;
            $balance->save();

            return $balance->refresh();
        });
    }

    public static function inclusiveDays(string $from, string $to): int
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        return max(1, (int) $start->diffInDays($end) + 1);
    }
}
