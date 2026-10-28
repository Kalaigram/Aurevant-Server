<?php

declare(strict_types=1);

namespace App\Application\Hr\UseCases;

use App\Application\Audit\Audit;
use App\Application\Hr\DTOs\ShiftSwapDTO;
use App\Application\Notify\Notifier;
use App\Application\Schedule\UseCases\ViewSchedule;
use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Models\Company;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\Access;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class ManageShiftSwaps
{
    public function __construct(
        private ViewSchedule $schedule,
        private ScheduleRepositoryInterface $schedules,
    ) {}

    public function canManage(User $actor): bool
    {
        return $actor->is_super_admin || $actor->can('shift.kelola');
    }

    /** @return LengthAwarePaginator<int, ShiftSwapDTO> */
    public function list(
        Company $company,
        User $actor,
        int $perPage,
        ?int $userId,
        ?string $status,
    ): LengthAwarePaginator {
        $manage = $this->canManage($actor);

        if (! $manage && $userId !== null && $userId !== $actor->id) {
            abort(403, 'Akses ditolak.');
        }

        return ShiftSwap::query()
            ->with(['requester', 'target', 'requesterShift', 'targetShift'])
            ->where('company_id', $company->id)
            ->when(! $manage, fn ($query) => $query->where(function ($scope) use ($actor): void {
                $scope->where('requester_id', $actor->id)
                    ->orWhere('target_user_id', $actor->id);
            }))
            ->when($manage && $userId !== null, fn ($query) => $query->where(function ($scope) use ($userId): void {
                $scope->where('requester_id', $userId)
                    ->orWhere('target_user_id', $userId);
            }))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (ShiftSwap $row) => ShiftSwapDTO::fromModel($row));
    }

    public function create(
        Company $company,
        User $actor,
        User $target,
        string $date,
        array $attributes,
    ): ShiftSwapDTO {
        if ($target->id === $actor->id) {
            throw ValidationException::withMessages([
                'target_user_id' => 'Pilih karyawan lain untuk tukar shift.',
            ]);
        }

        if (! Access::hasActiveAssignment($target, $company)) {
            abort(404);
        }

        $requesterShiftId = array_key_exists('requester_shift_id', $attributes)
            ? ($attributes['requester_shift_id'] === null ? null : (int) $attributes['requester_shift_id'])
            : $this->shiftIdOn($company, $actor, $date);
        $targetShiftId = array_key_exists('target_shift_id', $attributes)
            ? ($attributes['target_shift_id'] === null ? null : (int) $attributes['target_shift_id'])
            : $this->shiftIdOn($company, $target, $date);

        foreach ([$requesterShiftId, $targetShiftId] as $shiftId) {
            if ($shiftId !== null && $this->schedules->findShift($company, $shiftId) === null) {
                throw ValidationException::withMessages([
                    'shift_id' => 'Shift tidak ada.',
                ]);
            }
        }

        if ($requesterShiftId === $targetShiftId) {
            throw ValidationException::withMessages([
                'shift_id' => 'Shift kedua karyawan sama, tidak ada yang bisa ditukar.',
            ]);
        }

        $duplicate = ShiftSwap::query()
            ->where('company_id', $company->id)
            ->where('date', $date)
            ->where('status', 'diajukan')
            ->where(function ($query) use ($actor, $target): void {
                $query->whereIn('requester_id', [$actor->id, $target->id])
                    ->orWhereIn('target_user_id', [$actor->id, $target->id]);
            })
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'date' => 'Sudah ada pengajuan tukar shift aktif pada tanggal tersebut.',
            ]);
        }

        $swap = ShiftSwap::query()->create([
            'company_id' => $company->id,
            'requester_id' => $actor->id,
            'target_user_id' => $target->id,
            'date' => $date,
            'requester_shift_id' => $requesterShiftId,
            'target_shift_id' => $targetShiftId,
            'note' => $attributes['note'] ?? null,
            'status' => 'diajukan',
        ]);

        Audit::record($company, null, 'tukar_shift.ajukan', 'shift_swap', $swap->id);
        Notifier::toUser(
            $company,
            $target,
            'tukar_shift.diajukan',
            $actor->name.' mengajukan tukar shift '.$date.'.',
            $attributes['note'] ?? null,
            '/hr',
        );
        Notifier::toPermissioned(
            $company,
            ['shift.kelola'],
            'tukar_shift.diajukan',
            'Tukar shift '.$actor->name.' / '.$target->name.' menunggu persetujuan.',
            $date,
            '/hr',
            [$actor->id, $target->id],
        );

        return ShiftSwapDTO::fromModel($swap->refresh()->load(['requester', 'target', 'requesterShift', 'targetShift']));
    }

    public function decide(
        Company $company,
        ShiftSwap $swap,
        User $decider,
        bool $approve,
    ): ShiftSwapDTO {
        if ($swap->status !== 'diajukan') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan tukar shift sudah diproses.',
            ]);
        }

        $swap->status = $approve ? 'disetujui' : 'ditolak';
        $swap->decided_by = $decider->id;
        $swap->decided_at = now();
        $swap->save();

        if ($approve) {
            $date = $swap->date->toDateString();
            $this->schedules->upsertOverride(
                $company,
                $swap->requester,
                $date,
                $swap->target_shift_id,
                'Tukar shift #'.$swap->id,
            );
            $this->schedules->upsertOverride(
                $company,
                $swap->target,
                $date,
                $swap->requester_shift_id,
                'Tukar shift #'.$swap->id,
            );
        }

        Audit::record(
            $company, null, $approve ? 'tukar_shift.setujui' : 'tukar_shift.tolak',
            'shift_swap', $swap->id,
            ['status' => 'diajukan'],
            ['status' => $swap->status],
        );

        $title = 'Tukar shift '.$swap->date->toDateString().' '.($approve ? 'disetujui' : 'ditolak').'.';
        foreach ([$swap->requester, $swap->target] as $user) {
            Notifier::toUser(
                $company,
                $user,
                $approve ? 'tukar_shift.disetujui' : 'tukar_shift.ditolak',
                $title,
                null,
                '/hr',
            );
        }

        return ShiftSwapDTO::fromModel($swap->refresh()->load(['requester', 'target', 'requesterShift', 'targetShift']));
    }

    public function find(Company $company, int $id): ?ShiftSwap
    {
        return ShiftSwap::query()
            ->with(['requester', 'target', 'requesterShift', 'targetShift'])
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    private function shiftIdOn(Company $company, User $user, string $date): ?int
    {
        $days = ($this->schedule)($company, $user, $date, $date);

        return $days[0]->shiftId ?? null;
    }
}
