<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'sale_item_id',
        'item_id',
        'qty',
        'condition',
        'refund_amount',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'refund_amount' => 'decimal:2',
        ];
    }

    public function retur(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'sales_return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
