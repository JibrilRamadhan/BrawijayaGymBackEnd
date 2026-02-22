<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Make username nullable (trial/harian don't need it)
            $table->string('username')->nullable()->change();

            // Make password nullable (trial/harian have empty password)
            $table->string('password')->nullable()->change();

            // Add name and phone directly to users table
            $table->string('name')->nullable()->after('email');
            $table->string('phone', 20)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
            $table->dropColumn(['name', 'phone']);
        });
    }
};
