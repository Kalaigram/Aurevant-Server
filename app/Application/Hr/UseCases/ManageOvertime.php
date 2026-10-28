<?php

declare(strict_types=1);

namespace App\Application\Hr\UseCases;

use App\Application\Audit\Audit;
use App\Application\Hr\DTOs\OvertimeDTO;
use App\Application\Notify\Notifier;
use App\Models\Company;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Support\Access;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class ManageOvertime
{
    public function canManage(User $actor): bool
    {
        return $actor->is_super_admin
            || $actor->can('absensi.approve')
            || $actor->can('karyawan.kelola');
    }

    /** @return LengthAwarePaginator<int, OvertimeDTO> */
    public function list(
        Company $company,
        User $actor,
        int $perPage,
        ?int $userId,
        ?string $status,
        ?string $month,
    ): LengthAwarePaginator {
        if (! $this->canManage($actor)) {
            if ($userId !== null && $userId !== $actor->id) {
                abort(403, 'Akses ditolak.');
            }
            $userId = $actor->id;
        }

        return OvertimeRequest::query()
            ->with('user')
            ->where('company_id', $company->id)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($month !== null, function ($query) use ($month): void {
                $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $query->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (OvertimeRequest $row) => OvertimeDTO::fromModel($row));
    }

    public function create(
        Company $company,
        User $actor,
        User $target,
        string $date,
        string $startTime,
        string $endTime,
        string $reason,
    ): OvertimeDTO {
        if (! $this->canManage($actor) && $target->id !== $actor->id) {
            abort(403, 'Akses ditolak.');
        }

        if (! Access::hasActiveAssignment($target, $company)) {
            abort(404);
        }

        $minutes = $this->minutesBetween($startTime, $endTime);

        $overtime = OvertimeRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $target->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'minutes' => $minutes,
            'reason' => $reason,
            'status' => 'diajukan',
        ]);

        Audit::record($company, null, 'lembur.ajukan', 'overtime_request', $overtime->id);
        Notifier::toPermissioned(
            $company,
            ['absensi.approve', 'karyawan.kelola'],
            'lembur.diajukan',
            'Pengajuan lembur '.$target->name.' menunggu persetujuan.',
            $date.' - '.$minutes.' menit',
            '/hr',
            [$actor->id],
        );

        return OvertimeDTO::fromModel($overtime->load('user'));
    }

    public function decide(
        Company $company,
        OvertimeRequest $overtime,
        User $decider,
        bool $approve,
        ?string $note,
    ): OvertimeDTO {
        if ($overtime->status !== 'diajukan') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan lembur sudah diproses.',
            ]);
        }

        $overtime->status = $approve ? 'disetujui' : 'ditolak';
        $overtime->decided_by = $decider->id;
        $overtime->decided_at = now();
        $overtime->note = $note;
        $overtime->save();

        Audit::record(
            $company, null, $approve ? 'lembur.setujui' : 'lembur.tolak',
            'overtime_request', $overtime->id,
            ['status' => 'diajukan'],
            ['status' => $overtime->status],
        );

        Notifier::toUser(
            $company,
            $overtime->user,
            $approve ? 'lembur.disetujui' : 'lembur.ditolak',
            'Lembur '.$overtime->date->toDateString().' '.($approve ? 'disetujui' : 'ditolak').'.',
            $note,
            '/hr',
        );

        return OvertimeDTO::fromModel($overtime->refresh()->load('user'));
    }

    public function find(Company $company, int $id): ?OvertimeRequest
    {
        return OvertimeRequest::query()
            ->with('user')
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    private function minutesBetween(string $startTime, string $endTime): int
    {
        $start = Carbon::createFromFormat('H:i', $startTime);
        $end = Carbon::createFromFormat('H:i', $endTime);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $minutes = (int) $start->diffInMinutes($end);

        if ($minutes <= 0 || $minutes > 1440) {
            throw ValidationException::withMessages([
                'end_time' => 'Durasi lembur tidak valid.',
            ]);
        }

        return $minutes;
    }
}
