<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('expense_category_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('recurring_expense_id')->nullable()->after('user_id')
                ->constrained('recurring_expenses')->nullOnDelete();
            $table->string('proof_path', 500)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_expense_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('proof_path');
        });
    }
};
