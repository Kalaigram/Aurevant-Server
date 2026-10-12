<?php

declare(strict_types=1);

namespace App\Application\Import\UseCases;

use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Models\Company;

final class ExportItems
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {}

    /** @return list<array<string, string>> */
    public function __invoke(Company $company): array
    {
        $rows = [];
        foreach ($company->items()->with('category')->orderBy('code')->cursor() as $item) {
            $rows[] = [
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type,
                'unit' => (string) ($item->unit ?? ''),
                'category' => (string) ($item->category?->name ?? ''),
                'status' => $item->status,
            ];
        }

        return $rows;
    }
}
