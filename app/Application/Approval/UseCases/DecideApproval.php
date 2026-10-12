<?php

declare(strict_types=1);

namespace App\Application\Approval\UseCases;

use App\Application\Approval\DTOs\ApprovalDTO;
use App\Application\Audit\Audit;
use App\Application\Purchase\UseCases\TransitionPurchaseOrder;
use App\Application\Sale\UseCases\CreateReturn;
use App\Application\Sale\UseCases\CreateSale;
use App\Application\Sale\UseCases\VoidSale;
use App\Application\Stock\UseCases\ApproveOpname;
use App\Application\Stock\UseCases\TransitionTransfer;
use App\Domain\Approval\Services\ApprovalActions;
use App\Models\Approval;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DecideApproval
{
    public function __construct(
        private VoidSale $voidSale,
        private CreateSale $createSale,
        private CreateReturn $createReturn,
        private TransitionPurchaseOrder $purchaseFlow,
        private TransitionTransfer $transferFlow,
        private ApproveOpname $approveOpname,
    ) {}

    public function decide(Company $company, Approval $approval, User $decider, bool $approved, ?string $note): ApprovalDTO
    {
        $meta = ApprovalActions::find($approval->action) ?? throw ValidationException::withMessages([
            'action' => 'Aksi tidak dikenal.',
        ]);

        if (! $approval->isPending()) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan sudah diputuskan.',
            ]);
        }

        Access::require($decider, $company, $meta['decide']);

        if ($approval->branch_id !== null) {
            Access::requireBranch($decider, $company, $approval->branch_id);
        }

        return DB::transaction(function () use ($company, $approval, $decider, $approved, $note, $meta) {
            if ($approved) {
                $this->execute($company, $approval, $decider);
            }

            $approval->status = $approved ? 'disetujui' : 'ditolak';
            $approval->decided_by = $decider->id;
            $approval->decided_at = now();
            $approval->note = $note ?? $approval->note;
            $approval->save();

            Audit::record(
                $company, $approval->branch_id,
                $approved ? 'approval.setujui' : 'approval.tolak',
                'approval', $approval->id,
            );

            return ApprovalDTO::fromModel($approval->refresh(), $meta);
        });
    }

    private function execute(Company $company, Approval $approval, User $decider): void
    {
        $payload = $approval->payload ?? [];

        match ($approval->action) {
            'sale.void' => $this->executeVoid($company, $approval, $payload),
            'sale.discount' => $this->executeDiscount($company, $approval, $payload),
            'sale.refund' => $this->executeRefund($company, $approval, $payload),
            'po.approve' => $this->purchaseFlow->approve($this->purchaseOrder($company, $approval)),
            'transfer.approve' => $this->transferFlow->approve($this->transfer($company, $approval)),
            'opname.approve' => $this->approveOpname->__invoke($this->opname($company, $approval)),
            default => throw ValidationException::withMessages(['action' => 'Aksi tidak dikenal.']),
        };
    }

    private function executeVoid(Company $company, Approval $approval, array $payload): void
    {
        $sale = $this->sale($company, $approval);
        $branch = $this->branch($company, $sale->branch_id);
        ($this->voidSale)($company, $branch, $sale, (string) ($payload['reason'] ?? 'Disetujui'));
    }

    private function executeDiscount(Company $company, Approval $approval, array $payload): void
    {
        $branch = $this->branch($company, (int) $payload['branch_id']);
        $cashier = User::query()->whereKey($approval->requested_by)->first() ?? abort(422);

        ($this->createSale)(
            $company,
            $branch,
            $cashier,
            $payload['lines'],
            $payload['payments'],
            $payload['idempotency_key'] ?? null,
            isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
        );
    }

    private function executeRefund(Company $company, Approval $approval, array $payload): void
    {
        $sale = $this->sale($company, $approval);
        $branch = $this->branch($company, $sale->branch_id);
        ($this->createReturn)(
            $company,
            $branch,
            $sale,
            $payload['lines'],
            (string) $payload['reason'],
            (string) $payload['refund_method'],
        );
    }

    private function sale(Company $company, Approval $approval): Sale
    {
        return Sale::query()->where('company_id', $company->id)->whereKey($approval->entity_id)->first()
            ?? throw ValidationException::withMessages(['entity_id' => 'Nota tidak ada.']);
    }

    private function purchaseOrder(Company $company, Approval $approval): PurchaseOrder
    {
        return PurchaseOrder::query()->where('company_id', $company->id)->whereKey($approval->entity_id)->first()
            ?? throw ValidationException::withMessages(['entity_id' => 'PO tidak ada.']);
    }

    private function transfer(Company $company, Approval $approval): StockTransfer
    {
        return StockTransfer::query()->where('company_id', $company->id)->whereKey($approval->entity_id)->first()
            ?? throw ValidationException::withMessages(['entity_id' => 'Transfer tidak ada.']);
    }

    private function opname(Company $company, Approval $approval): StockOpname
    {
        return StockOpname::query()->where('company_id', $company->id)->whereKey($approval->entity_id)->first()
            ?? throw ValidationException::withMessages(['entity_id' => 'Opname tidak ada.']);
    }

    private function branch(Company $company, int $branchId): Branch
    {
        return $company->branches()->whereKey($branchId)->first()
            ?? throw ValidationException::withMessages(['branch_id' => 'Cabang tidak ada.']);
    }
}
