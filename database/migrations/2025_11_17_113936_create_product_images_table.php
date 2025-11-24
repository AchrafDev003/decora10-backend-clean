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
        Schema::create('product_images', function (Blueprint $table) {
            $table->bigIncrements('id'); // id Primaria AUTO_INCREMENT
            $table->unsignedBigInteger('product_id'); // relación con productos
            $table->string('image_path'); // ruta de la imagen
            $table->integer('position')->default(0); // posición para ordenar imágenes
            $table->timestamps(); // created_at y updated_at

            // Índice y clave foránea
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('product_id'); // índice para búsquedas rápidas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
