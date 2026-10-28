<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 15, 3)->default(1);
            $table->timestamps();

            $table->unique(['service_package_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_package_items');
    }
};
