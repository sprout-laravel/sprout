<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bud_config_store', static function (Blueprint $table) {
            $table->id();

            $table->string('tenancy');
            $table->string('tenant_id');
            $table->string('service');
            $table->string('name');
            $table->longText('config');

            $table->timestamps();

            $table->unique(['tenancy', 'tenant_id', 'service', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bud_config_store');
    }
};
