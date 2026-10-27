<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_return_id',
        'goods_receipt_item_id',
        'item_id',
        'qty',
        'unit_cost',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
