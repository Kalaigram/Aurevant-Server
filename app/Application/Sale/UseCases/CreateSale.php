<?php

declare(strict_types=1);

namespace App\Application\Sale\UseCases;

use App\Application\Report\UseCases\RebuildDailySummary;
use App\Application\Sale\DTOs\SaleDTO;
use App\Application\Stock\UseCases\RecordStockMovement;
use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Sale\Repositories\CashShiftRepositoryInterface;
use App\Domain\Sale\Repositories\SaleRepositoryInterface;
use App\Domain\Sale\Repositories\SaleSequenceRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateSale
{
    private const PAYMENT_METHODS = ['tunai', 'transfer', 'qris', 'ewallet', 'dp'];

    public function __construct(
        private SaleRepositoryInterface $sales,
        private CashShiftRepositoryInterface $shifts,
        private SaleSequenceRepositoryInterface $sequences,
        private ItemRepositoryInterface $items,
        private ItemPriceRepositoryInterface $prices,
        private RecordStockMovement $recordMovement,
        private RebuildDailySummary $rebuildSummary,
    ) {}

    /**
     * @param  list<array{item_id: int, qty: float, discount_percent?: float, discount_amount?: float, tax_percent?: float}>  $lines
     * @param  list<array{method: string, amount: float, tendered?: float|null, reference?: string|null}>  $payments
     * @return array{sale: SaleDTO, duplicate: bool}
     */
    public function __invoke(
        Company $company,
        Branch $branch,
        User $cashier,
        array $lines,
        array $payments,
        ?string $idempotencyKey,
        ?int $customerId = null,
        ?int $serviceOrderId = null,
    ): array {
        $shift = $this->shifts->findOpenForBranch($branch);
        if ($shift === null) {
            throw ValidationException::withMessages([
                'shift' => 'Belum ada shift terbuka di cabang ini.',
            ]);
        }

        if ($idempotencyKey !== null) {
            $existing = $this->sales->findByIdempotency($company->id, $idempotencyKey);
            if ($existing !== null) {
                return ['sale' => SaleDTO::fromModel($existing), 'duplicate' => true];
            }
        }

        $this->validatePayments($payments);

        return DB::transaction(function () use ($company, $branch, $cashier, $shift, $lines, $payments, $idempotencyKey, $customerId, $serviceOrderId) {
            $number = $this->nextNumber($branch);
            $computed = $this->computeLines($company, $branch, $lines);
            $this->guardPaymentsTotal($computed['grandTotal'], $payments);

            $stockNotes = 'Nota '.$number;
            $hppByItem = $this->deductStock($company, $branch, $computed['stockJobs'], $stockNotes);
            foreach ($computed['rows'] as &$row) {
                $row['hpp_amount'] = $hppByItem[$row['item_id']] ?? null;
            }
            unset($row);

            $sale = $this->sales->createWithRelations([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'shift_id' => $shift->id,
                'user_id' => $cashier->id,
                'customer_id' => $customerId,
                'service_order_id' => $serviceOrderId,
                'number' => $number,
                'idempotency_key' => $idempotencyKey,
                'status' => 'lunas',
                'subtotal' => $computed['subtotal'],
                'discount_total' => $computed['discountTotal'],
                'tax_total' => $computed['taxTotal'],
                'grand_total' => $computed['grandTotal'],
            ], $computed['rows'], $this->paymentRows($payments));

            ($this->rebuildSummary)($branch, now()->toDateString());

            return ['sale' => SaleDTO::fromModel($sale), 'duplicate' => false];
        });
    }

    /** @return array{rows: list<array<string, mixed>>, stockJobs: list<array<string, mixed>>, subtotal: float, discountTotal: float, taxTotal: float, grandTotal: float} */
    private function computeLines(Company $company, Branch $branch, array $lines): array
    {
        $rows = [];
        $stockJobs = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $item = $this->items->findForCompany($company, (int) $line['item_id']) ?? abort(404);
            $priceRow = $this->prices->findForItemBranch($item->id, $branch->id);
            if ($priceRow === null) {
                throw ValidationException::withMessages([
                    'items' => 'Harga item '.$item->code.' belum diatur di cabang ini.',
                ]);
            }

            $qty = (float) $line['qty'];
            $price = (float) $priceRow->price;
            $discPct = (float) ($line['discount_percent'] ?? 0);
            $discAmt = (float) ($line['discount_amount'] ?? 0);
            $taxPct = (float) ($line['tax_percent'] ?? 0);

            if ($discPct > 0 && $discAmt > 0) {
                throw ValidationException::withMessages([
                    'items' => 'Diskon persen dan nominal tidak boleh diisi bersamaan.',
                ]);
            }

            $gross = round($price * $qty, 2);
            $disc = $discPct > 0 ? round($gross * $discPct / 100, 2) : round($discAmt, 2);
            if ($disc > $gross) {
                throw ValidationException::withMessages([
                    'items' => 'Diskon item '.$item->code.' melebihi harga.',
                ]);
            }

            $net = $gross - $disc;
            $tax = round($net * $taxPct / 100, 2);
            $lineTotal = round($net + $tax, 2);

            $subtotal += $gross;
            $discountTotal += $disc;
            $taxTotal += $tax;

            $rows[] = [
                'item_id' => $item->id,
                'qty' => $qty,
                'price' => $price,
                'discount_percent' => $discPct,
                'discount_amount' => $discAmt,
                'tax_percent' => $taxPct,
                'line_total' => $lineTotal,
                'hpp_amount' => null,
            ];

            if ($item->type === 'barang' && (bool) $item->track_stock) {
                $stockJobs[] = ['item_id' => $item->id, 'type' => 'penjualan', 'qty' => $qty, 'unit_cost' => null];
            }

            if ($item->type === 'jasa') {
                $item->loadMissing('consumables');
                foreach ($item->consumables as $recipe) {
                    $stockJobs[] = [
                        'item_id' => $recipe->material_item_id,
                        'type' => 'pemakaian_bahan',
                        'qty' => (float) $recipe->qty * $qty,
                        'unit_cost' => null,
                    ];
                }
            }
        }

        return [
            'rows' => $rows,
            'stockJobs' => $stockJobs,
            'subtotal' => round($subtotal, 2),
            'discountTotal' => round($discountTotal, 2),
            'taxTotal' => round($taxTotal, 2),
            'grandTotal' => round($subtotal - $discountTotal + $taxTotal, 2),
        ];
    }

    /** @param list<array<string, mixed>> $payments */
    private function validatePayments(array $payments): void
    {
        foreach ($payments as $payment) {
            if (! in_array($payment['method'], self::PAYMENT_METHODS, true)) {
                throw ValidationException::withMessages([
                    'payments' => 'Metode bayar '.$payment['method'].' tidak dikenal.',
                ]);
            }

            if ($payment['method'] !== 'tunai' && empty($payment['reference'])) {
                throw ValidationException::withMessages([
                    'payments' => 'Referensi wajib untuk bayar '.$payment['method'].'.',
                ]);
            }
        }
    }

    /** @param list<array<string, mixed>> $payments */
    private function guardPaymentsTotal(float $grandTotal, array $payments): void
    {
        $paid = 0.0;
        foreach ($payments as $payment) {
            $paid += (float) $payment['amount'];
        }

        if (abs(round($paid, 2) - $grandTotal) > 0.009) {
            throw ValidationException::withMessages([
                'payments' => 'Total bayar harus sama dengan total nota.',
            ]);
        }
    }

    /** @param list<array<string, mixed>> $payments */
    private function paymentRows(array $payments): array
    {
        return array_map(fn ($payment) => [
            'method' => $payment['method'],
            'amount' => $payment['amount'],
            'tendered' => $payment['tendered'] ?? null,
            'reference' => $payment['reference'] ?? null,
        ], $payments);
    }

    /** @param list<array{item_id: int, type: string, qty: float, unit_cost: float|null}> $jobs */
    private function deductStock(Company $company, Branch $branch, array $jobs, string $note): array
    {
        $hpp = [];
        foreach ($jobs as $job) {
            $movement = ($this->recordMovement)(
                $company, $branch, $job['item_id'], $job['type'], $job['qty'], $job['unit_cost'], $note,
            );
            if ($movement->hppAmount !== null) {
                $hpp[$job['item_id']] = ($hpp[$job['item_id']] ?? 0) + (float) $movement->hppAmount;
            }
        }

        return $hpp;
    }

    private function nextNumber(Branch $branch): string
    {
        $date = now()->format('Y-m-d');
        $seq = $this->sequences->nextNumber($branch, $date);

        return strtoupper($branch->code).'-'.now()->format('ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
