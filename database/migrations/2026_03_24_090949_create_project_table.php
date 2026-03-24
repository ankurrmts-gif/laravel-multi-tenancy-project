<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('name')->nullable();

            $table->string('summary')->nullable();

            $table->string('cover_image')->nullable();

            $table->integer('feature_ids')->nullable();

            $table->timestamps();

        });

    }

    public function down(): void
    {
        Schema::dropIfExists('project');
    }

};