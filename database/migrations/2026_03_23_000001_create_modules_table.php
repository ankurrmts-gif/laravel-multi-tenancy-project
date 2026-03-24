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
            $table->unsignedBigInteger('parent_menu')->nullable();
            $table->boolean('status')->default(true);
            $table->string('icon')->nullable();
            $table->string('user_type')->default('Admin'); // Admin or Agency
            $table->integer('order_number')->default(0);
            $table->string('tenant_id');
            $table->json('actions')->nullable(); // {"create": true, "edit": true, etc.}
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
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