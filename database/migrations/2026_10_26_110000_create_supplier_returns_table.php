<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->decimal('total', 15, 2);
            $table->string('reason');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_returns');
    }
};
