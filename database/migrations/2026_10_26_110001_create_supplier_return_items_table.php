<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
    }
};
