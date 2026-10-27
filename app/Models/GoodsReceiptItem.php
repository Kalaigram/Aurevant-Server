<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'item_id',
        'qty_received',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }
}
