<?php

declare(strict_types=1);

namespace App\Application\Sale\UseCases;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Sale;

/** Susun data struk siap cetak. Tanpa HTTP, tanpa framework cetak. */
final class PrintReceipt
{
    /** @return array<string, mixed> */
    public function __invoke(Company $company, Branch $branch, Sale $sale): array
    {
        $sale->loadMissing(['items.item', 'payments', 'user', 'customer']);

        $lines = [];
        foreach ($sale->items as $row) {
            $lines[] = [
                'nama' => (string) ($row->item?->name ?? 'Item #'.$row->item_id),
                'qty' => (string) $row->qty,
                'harga' => (string) $row->price,
                'total' => (string) $row->line_total,
            ];
        }

        $pays = [];
        foreach ($sale->payments as $pay) {
            $pays[] = [
                'cara' => (string) $pay->method,
                'nominal' => (string) $pay->amount,
                'diterima' => $pay->tendered === null ? null : (string) $pay->tendered,
            ];
        }

        return [
            'perusahaan' => $company->name,
            'cabang' => $branch->name,
            'nomor' => $sale->number,
            'tanggal' => $sale->created_at?->toDateTimeString(),
            'kasir' => (string) ($sale->user?->name ?? ''),
            'pelanggan' => $sale->customer?->name,
            'status' => $sale->status,
            'baris' => $lines,
            'subtotal' => (string) $sale->subtotal,
            'diskon' => (string) $sale->discount_total,
            'pajak' => (string) $sale->tax_total,
            'total' => (string) $sale->grand_total,
            'bayar' => $pays,
        ];
    }
}
