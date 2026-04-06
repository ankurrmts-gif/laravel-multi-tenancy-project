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
        Schema::create('datatable_settings', function (Blueprint $table) {
            $table->id();
            $table->string('table_key'); // users / products / projects_list
            $table->string('module_type')->default('static'); // static / dynamic
            $table->unsignedBigInteger('module_id')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datatable_settings');
    }
};
