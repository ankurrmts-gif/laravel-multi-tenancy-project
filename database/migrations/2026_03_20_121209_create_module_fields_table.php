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
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->foreignId('column_type_id')->constrained('column_types');
            $table->string('db_column');
            $table->string('label');
            $table->string('tooltip_text')->nullable();
            $table->string('validation')->nullable();
            $table->string('default_value')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('is_ckeditor')->default(0);
            $table->tinyInteger('is_multiple')->default(0);
            $table->string('model_name')->nullable(); // For relationships
            $table->string('model_field_name')->nullable();
            $table->integer('max_file_size')->nullable();
            $table->bigInteger('order_number')->default(0);
            $table->timestamps();
            $table->softDeletes();
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
