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
        Schema::create('module_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id');
            $table->unsignedBigInteger('column_type_id');
            $table->string('db_column');
            $table->string('label');
            $table->text('tooltip_text')->nullable();
            $table->string('validation')->nullable();
            $table->string('default_value')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('is_ckeditor')->default(false);
            $table->boolean('is_multiple')->default(false);
            $table->string('model_name')->nullable();
            $table->string('model_field_name')->nullable();
            $table->integer('max_file_size')->nullable();
            $table->integer('order_number')->default(0);
            $table->boolean('is_checked')->default(false);
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
            $table->foreign('column_type_id')->references('id')->on('column_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_fields');
    }
};