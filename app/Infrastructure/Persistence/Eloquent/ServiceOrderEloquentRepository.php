<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ServiceOrder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ServiceOrderEloquentRepository implements ServiceOrderRepositoryInterface
{
    public function paginateForBranch(
        Branch $branch,
        int $perPage,
        ?string $status = null,
        ?string $q = null,
        ?string $scheduledFrom = null,
        ?string $scheduledTo = null,
    ): LengthAwarePaginator {
        $scheduled = $scheduledFrom !== null || $scheduledTo !== null;

        return ServiceOrder::query()
            ->with(['items.item', 'customer', 'assignee', 'servicePackage'])
            ->where('branch_id', $branch->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->whereHas('customer', fn ($customer) => $customer
                    ->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->when($scheduledFrom !== null, fn ($query) => $query
                ->where('scheduled_at', '>=', $scheduledFrom.' 00:00:00'))
            ->when($scheduledTo !== null, fn ($query) => $query
                ->where('scheduled_at', '<=', $scheduledTo.' 23:59:59'))
            ->when($scheduled, fn ($query) => $query->orderBy('scheduled_at'))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForBranch(Branch $branch, int $id): ?ServiceOrder
    {
        return ServiceOrder::query()
            ->with(['items.item', 'customer', 'assignee', 'servicePackage'])
            ->where('branch_id', $branch->id)
            ->whereKey($id)
            ->first();
    }

    public function createWithItems(Company $company, Branch $branch, array $attributes, array $items): ServiceOrder
    {
        $order = ServiceOrder::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            ...$attributes,
        ]);
        $order->items()->createMany($items);

        return $order->refresh()->load(['items.item', 'customer', 'assignee', 'servicePackage']);
    }

    public function save(ServiceOrder $order): ServiceOrder
    {
        $order->save();

        return $order->refresh()->load(['items.item', 'customer', 'assignee', 'servicePackage']);
    }
}
