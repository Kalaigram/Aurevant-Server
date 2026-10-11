<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('barang');
            $table->string('unit')->nullable();
            $table->boolean('track_stock')->default(true);
            $table->boolean('has_variants')->default(false);
            $table->boolean('track_batch')->default(false);
            $table->boolean('track_serial')->default(false);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
