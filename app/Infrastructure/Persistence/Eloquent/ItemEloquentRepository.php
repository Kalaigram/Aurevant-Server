<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Models\Company;
use App\Models\Item;
use Illuminate\Pagination\LengthAwarePaginator;

final class ItemEloquentRepository implements ItemRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage, ?string $type = null, ?int $categoryId = null, ?string $q = null): LengthAwarePaginator
    {
        return $company->items()
            ->with(['prices', 'category'])
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(fn ($item) => $item
                    ->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like));
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?Item
    {
        return $company->items()->with(['prices', 'category'])->whereKey($id)->first();
    }

    public function findByCodeForCompany(Company $company, string $code): ?Item
    {
        return $company->items()->where('code', $code)->first();
    }

    public function createForCompany(Company $company, array $attributes): Item
    {
        return $company->items()->create($attributes)->refresh();
    }

    public function update(Item $item, array $attributes): Item
    {
        $item->update($attributes);

        return $item->refresh()->load('prices');
    }

    public function delete(Item $item): void
    {
        $item->delete();
    }
}
