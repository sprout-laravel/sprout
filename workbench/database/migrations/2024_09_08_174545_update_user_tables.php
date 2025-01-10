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
        Schema::table('password_reset_tokens', static function (Blueprint $table) {
            $table->string('tenancy')->nullable();
            $table->string('tenant_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('password_reset_tokens', static function (Blueprint $table) {
            $table->dropColumn('tenancy');
            $table->dropColumn('tenant_id');
        });
    }
};
