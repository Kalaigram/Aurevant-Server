<?php

declare(strict_types=1);

namespace App\Application\Approval\UseCases;

use App\Application\Approval\DTOs\ApprovalDTO;
use App\Application\Audit\Audit;
use App\Application\Notify\Notifier;
use App\Domain\Approval\Services\ApprovalActions;
use App\Domain\Approval\Services\ApprovalLedger;
use App\Models\Approval;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Access;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class RequestApproval
{
    /** @return LengthAwarePaginator<int, ApprovalDTO> */
    public function list(Company $company, int $perPage, ?string $status, ?string $action): LengthAwarePaginator
    {
        return Approval::query()
            ->where('company_id', $company->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($action !== null, fn ($query) => $query->where('action', $action))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($row) => ApprovalDTO::fromModel($row, ApprovalActions::find($row->action)));
    }

    public function find(Company $company, int $id): ?Approval
    {
        return Approval::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function request(
        Company $company,
        User $requester,
        string $action,
        ?int $entityId,
        array $payload,
        ?string $note,
    ): ApprovalDTO {
        $meta = ApprovalActions::find($action) ?? throw ValidationException::withMessages([
            'action' => 'Aksi tidak dikenal.',
        ]);

        Access::require($requester, $company, $meta['request']);

        $entityKey = ApprovalActions::entityKey($action);
        $entity = $entityId === null ? null : $this->resolveEntity($company, $meta, $entityKey, $entityId);

        if ($entityKey !== null && $entity === null) {
            throw ValidationException::withMessages([
                'entity_id' => 'Data tidak ada.',
            ]);
        }

        $branchId = $this->branchIdFor($entity, $payload);
        if ($branchId !== null) {
            Access::requireBranch($requester, $company, $branchId);
        }

        $this->assertPayload($action, $payload);
        $this->assertRequestable($action, $entity);

        if ($entityKey !== null) {
            $duplicate = Approval::query()
                ->where('company_id', $company->id)
                ->where('entity_type', $entityKey)
                ->where('entity_id', $entityId)
                ->where('action', $action)
                ->where('status', 'diajukan')
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'approval' => 'Sudah ada pengajuan aktif untuk data ini.',
                ]);
            }
        }

        $approval = ApprovalLedger::open(
            $company, $branchId, $entityKey, $entityId, $action, $requester, $payload, $note,
        );

        Audit::record($company, $branchId, 'approval.ajukan', 'approval', $approval->id);
        Notifier::toPermissioned(
            $company, [$meta['decide']], 'approval.diajukan',
            $meta['label'].' menunggu persetujuan.', null, '/approvals/'.$approval->id, $requester->id,
        );

        return ApprovalDTO::fromModel($approval->refresh(), $meta);
    }

    /** @param array{entity: class-string|null} $meta */
    private function resolveEntity(Company $company, array $meta, ?string $entityKey, int $entityId): ?object
    {
        if ($meta['entity'] === null || $entityKey === null) {
            return null;
        }

        $class = $meta['entity'];

        return $class::query()->where('company_id', $company->id)->whereKey($entityId)->first();
    }

    private function branchIdFor(?object $entity, array $payload): ?int
    {
        return match (true) {
            $entity instanceof Sale => $entity->branch_id,
            $entity instanceof PurchaseOrder => $entity->branch_id,
            $entity instanceof StockTransfer => $entity->from_branch_id,
            $entity instanceof StockOpname => $entity->branch_id,
            isset($payload['branch_id']) => (int) $payload['branch_id'],
            default => null,
        };
    }

    private function assertPayload(string $action, array $payload): void
    {
        match ($action) {
            'sale.void' => $this->need($payload, ['reason']),
            'sale.refund' => $this->need($payload, ['lines', 'reason', 'refund_method']),
            'sale.discount' => $this->need($payload, ['branch_id', 'lines', 'payments']),
            default => null,
        };

        if (in_array($action, ['sale.refund'], true)
            && isset($payload['refund_method'])
            && ! in_array($payload['refund_method'], ['tunai', 'transfer'], true)
        ) {
            throw ValidationException::withMessages([
                'refund_method' => 'Metode refund tunai/transfer.',
            ]);
        }
    }

    /** @param list<string> $keys */
    private function need(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                throw ValidationException::withMessages([
                    $key => 'Wajib diisi untuk aksi ini.',
                ]);
            }
        }
    }

    private function assertRequestable(string $action, ?object $entity): void
    {
        $status = $entity?->getAttribute('status');

        $ok = match ($action) {
            'sale.void', 'sale.refund' => $status === 'lunas',
            'po.approve', 'transfer.approve', 'opname.approve' => $status === 'diajukan',
            'sale.discount' => true,
            default => false,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'entity_id' => 'Status saat ini tidak bisa diajukan.',
            ]);
        }
    }
}
