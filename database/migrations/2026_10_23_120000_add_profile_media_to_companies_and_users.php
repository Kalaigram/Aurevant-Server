<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('tax_id', 32)->nullable()->after('legal_name');
            $table->string('email')->nullable()->after('tax_id');
            $table->string('phone', 30)->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('address');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_path');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_name',
                'tax_id',
                'email',
                'phone',
                'address',
                'logo_path',
            ]);
        });
    }
};
