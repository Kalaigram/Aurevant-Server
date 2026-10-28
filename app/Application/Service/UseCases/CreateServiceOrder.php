<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\ServiceOrderDTO;
use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Catalog\Repositories\ServicePackageRepositoryInterface;
use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

final class CreateServiceOrder
{
    public function __construct(
        private ServiceOrderRepositoryInterface $orders,
        private ItemRepositoryInterface $items,
        private ItemPriceRepositoryInterface $prices,
        private CustomerRepositoryInterface $customers,
        private ServicePackageRepositoryInterface $servicePackages,
    ) {}

    /**
     * @param  list<array{item_id: int, qty: float}>  $lines
     */
    public function __invoke(
        Company $company,
        Branch $branch,
        array $lines,
        ?int $customerId,
        ?string $scheduledAt,
        ?string $note,
        ?int $packageId = null,
    ): ServiceOrderDTO {
        if ($customerId !== null) {
            $this->customers->findForCompany($company, $customerId) ?? abort(404);
        }

        $rows = [];
        foreach ($lines as $line) {
            $item = $this->items->findForCompany($company, (int) $line['item_id']) ?? abort(404);

            if ($item->type !== 'jasa') {
                throw ValidationException::withMessages([
                    'items' => 'Item '.$item->code.' bukan jasa.',
                ]);
            }

            $price = $this->prices->findForItemBranch($item->id, $branch->id);
            if ($price === null) {
                throw ValidationException::withMessages([
                    'items' => 'Harga jasa '.$item->code.' belum diatur di cabang ini.',
                ]);
            }

            $rows[] = [
                'item_id' => $item->id,
                'qty' => $line['qty'],
                'price' => $price->price,
                'commission_amount' => 0,
            ];
        }

        $packageRows = $packageId !== null
            ? $this->packageRows($company, $branch, $packageId)
            : [];

        $explicitIds = array_column($rows, 'item_id');
        foreach ($packageRows as $row) {
            if (in_array($row['item_id'], $explicitIds, true)) {
                throw ValidationException::withMessages([
                    'items' => 'Jasa yang termasuk paket tidak boleh dipilih ulang pada baris terpisah.',
                ]);
            }
        }

        return ServiceOrderDTO::fromModel(
            $this->orders->createWithItems($company, $branch, [
                'customer_id' => $customerId,
                'service_package_id' => $packageId,
                'status' => 'baru',
                'deposit_amount' => 0,
                'scheduled_at' => $scheduledAt,
                'note' => $note,
            ], array_merge($packageRows, $rows))
        );
    }

    /**
     * @return list<array{item_id: int, qty: float, price: float, commission_amount: float}>
     */
    private function packageRows(Company $company, Branch $branch, int $packageId): array
    {
        $package = $this->servicePackages->findForCompany($company, $packageId) ?? abort(404);

        if ($package->status !== 'aktif') {
            throw ValidationException::withMessages([
                'package_id' => 'Paket jasa tidak aktif.',
            ]);
        }

        $package->loadMissing('items.item');

        if ($package->items->isEmpty()) {
            throw ValidationException::withMessages([
                'package_id' => 'Paket jasa belum memiliki jasa.',
            ]);
        }

        $lines = [];
        $normalTotal = 0.0;
        foreach ($package->items as $line) {
            $item = $line->relationLoaded('item') ? $line->item : null;

            if ($item === null || $item->type !== 'jasa') {
                throw ValidationException::withMessages([
                    'package_id' => 'Paket jasa berisi item yang tidak valid.',
                ]);
            }

            $price = $this->prices->findForItemBranch($item->id, $branch->id);
            if ($price === null) {
                throw ValidationException::withMessages([
                    'package_id' => 'Harga jasa '.$item->code.' di paket belum diatur di cabang ini.',
                ]);
            }

            $qty = (float) $line->qty;
            $normal = (float) $price->price * $qty;
            $normalTotal += $normal;

            $lines[] = [
                'item_id' => $item->id,
                'qty' => $qty,
                'normal' => $normal,
            ];
        }

        $packagePrice = (float) $package->price;
        $count = count($lines);
        $remaining = $packagePrice;
        $rows = [];

        foreach ($lines as $index => $line) {
            if ($index === $count - 1) {
                $allocated = $remaining;
            } elseif ($normalTotal > 0) {
                $allocated = round($packagePrice * ($line['normal'] / $normalTotal), 2);
            } else {
                $allocated = round($packagePrice / $count, 2);
            }

            $remaining = round($remaining - $allocated, 2);

            $rows[] = [
                'item_id' => $line['item_id'],
                'qty' => $line['qty'],
                'price' => $line['qty'] > 0 ? round($allocated / $line['qty'], 2) : 0.0,
                'commission_amount' => 0,
            ];
        }

        return $rows;
    }
}
