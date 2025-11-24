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
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id'); // id Primaria AUTO_INCREMENT
            $table->string('name')->unique(); // name varchar(255) con índice único
            $table->string('slug')->unique(); // slug varchar(255) con índice único
            $table->text('description')->nullable(); // description text nullable
            $table->string('image')->nullable(); // image varchar(255) nullable
            // Campos extra que normalmente se usan en productos
            $table->unsignedBigInteger('category_id')->nullable(); // relación con categoría (opcional)
            $table->decimal('price', 10, 2)->default(0); // precio del producto
            $table->integer('stock')->default(0); // cantidad en inventario
            $table->timestamps(); // created_at y updated_at

            // Índices y relaciones
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
