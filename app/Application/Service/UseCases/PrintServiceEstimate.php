<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Models\Branch;
use App\Models\Company;
use App\Models\ServiceOrder;

/** Susun data estimasi biaya jasa siap cetak. */
final class PrintServiceEstimate
{
    /** @return array<string, mixed> */
    public function __invoke(Company $company, Branch $branch, ServiceOrder $order): array
    {
        $order->loadMissing(['items.item', 'customer', 'assignee']);

        $lines = [];
        $total = 0.0;
        foreach ($order->items as $row) {
            $subtotal = (float) $row->qty * (float) $row->price;
            $total += $subtotal;
            $lines[] = [
                'nama' => (string) ($row->item?->name ?? 'Item #'.$row->item_id),
                'kode' => (string) ($row->item?->code ?? ''),
                'qty' => (string) $row->qty,
                'satuan' => (string) ($row->item?->unit ?? ''),
                'harga' => (string) $row->price,
                'total' => number_format($subtotal, 2, '.', ''),
            ];
        }

        return [
            'perusahaan' => $company->name,
            'alamat' => $company->address,
            'telepon' => $company->phone,
            'cabang' => $branch->name,
            'nomor' => 'EST-'.$order->id,
            'tanggal' => $order->created_at?->toDateTimeString(),
            'pelanggan' => $order->customer?->name ?? 'Tanpa nama',
            'telepon_pelanggan' => $order->customer?->phone,
            'jadwal' => $order->scheduled_at?->toDateTimeString(),
            'teknisi' => $order->assignee?->name,
            'catatan' => $order->note,
            'status' => $order->status,
            'baris' => $lines,
            'total' => number_format($total, 2, '.', ''),
            'dp' => (string) $order->deposit_amount,
            'sisa' => number_format(max(0, $total - (float) $order->deposit_amount), 2, '.', ''),
        ];
    }
}
