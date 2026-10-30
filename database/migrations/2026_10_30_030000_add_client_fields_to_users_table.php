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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email')->index();
            $table->string('role', 30)->nullable()->default('client')->after('phone')->index();
            $table->string('client_type', 30)->default('e_commerce')->after('role'); // e_commerce, entreprise, particulier
            $table->string('store_name')->nullable()->after('client_type');
            $table->string('city', 100)->nullable()->after('store_name')->index();
            $table->text('address')->nullable()->after('city');
            $table->boolean('is_active')->default(true)->after('address')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['role']);
            $table->dropIndex(['city']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'phone',
                'role',
                'client_type',
                'store_name',
                'city',
                'address',
                'is_active',
            ]);
        });
    }
};
