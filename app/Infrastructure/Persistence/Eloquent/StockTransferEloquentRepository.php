<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Stock\Repositories\StockTransferRepositoryInterface;
use App\Models\Company;
use App\Models\StockTransfer;
use Illuminate\Pagination\LengthAwarePaginator;

final class StockTransferEloquentRepository implements StockTransferRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage, ?string $status = null): LengthAwarePaginator
    {
        return StockTransfer::query()
            ->with(['items.item', 'fromBranch', 'toBranch'])
            ->where('company_id', $company->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?StockTransfer
    {
        return StockTransfer::query()
            ->with(['items.item', 'fromBranch', 'toBranch'])
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function createWithItems(Company $company, array $attributes, array $items): StockTransfer
    {
        $transfer = StockTransfer::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ]);
        $transfer->items()->createMany($items);

        return $transfer->refresh()->load(['items.item', 'fromBranch', 'toBranch']);
    }

    public function save(StockTransfer $transfer): StockTransfer
    {
        $transfer->save();

        return $transfer->refresh()->load(['items.item', 'fromBranch', 'toBranch']);
    }
}
