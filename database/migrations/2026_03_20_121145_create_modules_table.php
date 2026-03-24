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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('model_name');
            $table->string('slug')->unique();
            $table->string('menu_title');
            $table->integer('parent_menu')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('icon')->nullable();
            $table->enum('user_type', ['Admin', 'Customer']);
            $table->bigInteger('order_number')->default(0);
            $table->string('tenant_id')->index(); // Multi-tenancy support
            $table->json('actions'); // Create, Edit, Show, Delete
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
