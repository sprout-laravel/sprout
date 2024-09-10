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
        Schema::create('tenant_child2', static function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('tenant_relations', static function (Blueprint $table) {
            $table->foreignId('tenant_id')->references('id')->on('tenants');
            $table->foreignId('tenant_child2_id')->references('id')->on('tenant_child2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_child2');
        Schema::dropIfExists('tenant_relations');
    }
};
