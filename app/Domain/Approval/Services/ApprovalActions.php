<?php

declare(strict_types=1);

namespace App\Domain\Approval\Services;

use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockOpname;
use App\Models\StockTransfer;

/** Registry aksi yang lewat mesin approval: izin ajukan + izin putuskan + entitas. */
final class ApprovalActions
{
    /** @return array<string, array{label: string, request: string, decide: string, entity: class-string|null}> */
    public static function all(): array
    {
        return [
            'sale.void' => [
                'label' => 'Void nota', 'request' => 'transaksi.buat', 'decide' => 'transaksi.void',
                'entity' => Sale::class,
            ],
            'sale.discount' => [
                'label' => 'Nota diskon', 'request' => 'transaksi.buat', 'decide' => 'transaksi.diskon',
                'entity' => null,
            ],
            'sale.refund' => [
                'label' => 'Retur/refund', 'request' => 'retur.buat', 'decide' => 'retur.setujui',
                'entity' => Sale::class,
            ],
            'po.approve' => [
                'label' => 'Setujui PO', 'request' => 'po.buat', 'decide' => 'po.setujui',
                'entity' => PurchaseOrder::class,
            ],
            'transfer.approve' => [
                'label' => 'Setujui transfer', 'request' => 'stok.transfer', 'decide' => 'stok.transfer',
                'entity' => StockTransfer::class,
            ],
            'opname.approve' => [
                'label' => 'Setujui opname', 'request' => 'stok.opname', 'decide' => 'stok.opname',
                'entity' => StockOpname::class,
            ],
        ];
    }

    /** @return array{label: string, request: string, decide: string, entity: class-string|null}|null */
    public static function find(string $action): ?array
    {
        return self::all()[$action] ?? null;
    }

    public static function entityKey(string $action): ?string
    {
        return match ($action) {
            'sale.void', 'sale.refund' => 'sale',
            'po.approve' => 'purchase_order',
            'transfer.approve' => 'stock_transfer',
            'opname.approve' => 'stock_opname',
            default => null,
        };
    }
}
