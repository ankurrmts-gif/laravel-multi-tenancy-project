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
        Schema::create('module_field_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_field_id');
            $table->string('option_label');
            $table->string('option_value');
            $table->timestamps();

            $table->foreign('module_field_id')->references('id')->on('module_fields')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_field_options');
    }
};