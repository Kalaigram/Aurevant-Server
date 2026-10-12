<?php

declare(strict_types=1);

namespace App\Domain\Approval\Services;

use App\Models\Approval;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Jembatan modul → mesin approval. Dipanggil dari use case saat
 * diajukan / diputuskan lewat jalur lama, agar tercatat tunggal.
 */
final class ApprovalLedger
{
    public static function open(
        Company $company,
        ?int $branchId,
        ?string $entityType,
        ?int $entityId,
        string $action,
        User $requester,
        array $payload = [],
        ?string $note = null,
    ): Approval {
        return DB::transaction(function () use ($company, $branchId, $entityType, $entityId, $action, $requester, $payload, $note) {
            $existing = Approval::query()
                ->where('company_id', $company->id)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('action', $action)
                ->where('status', 'diajukan')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return Approval::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'status' => 'diajukan',
                'payload' => $payload === [] ? null : $payload,
                'requested_by' => $requester->id,
                'note' => $note,
            ]);
        });
    }

    public static function resolve(
        Company $company,
        ?string $entityType,
        ?int $entityId,
        string $action,
        User $decider,
        bool $approved,
        ?string $note = null,
    ): Approval {
        return DB::transaction(function () use ($company, $entityType, $entityId, $action, $decider, $approved, $note) {
            $row = Approval::query()
                ->where('company_id', $company->id)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('action', $action)
                ->where('status', 'diajukan')
                ->first();

            if ($row === null) {
                $row = new Approval([
                    'company_id' => $company->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'action' => $action,
                    'requested_by' => $decider->id,
                ]);
            }

            $row->status = $approved ? 'disetujui' : 'ditolak';
            $row->decided_by = $decider->id;
            $row->decided_at = now();
            $row->note = $note ?? $row->note;
            $row->save();

            return $row->refresh();
        });
    }
}
