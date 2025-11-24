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
        Schema::create('categories', function (Blueprint $table) {
            $table->bigIncrements('id'); // id Primaria AUTO_INCREMENT
            $table->string('name')->unique(); // name varchar(255) con índice único
            $table->string('slug')->unique(); // slug varchar(255) con índice único
            $table->text('description')->nullable(); // description text nullable
            $table->string('image')->nullable(); // image varchar(255) nullable
            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
