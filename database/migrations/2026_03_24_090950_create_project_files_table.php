<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_files', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->unsignedBigInteger('project_id');

            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();

            $table->timestamps();

            $table->foreign('project_id')
                ->references('id')
                ->on('project')
                ->onDelete('cascade');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
    }
};