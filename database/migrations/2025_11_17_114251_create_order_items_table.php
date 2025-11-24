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
        Schema::create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id'); // id Primaria AUTO_INCREMENT
            $table->unsignedBigInteger('order_id'); // relación con orders
            $table->unsignedBigInteger('product_id'); // relación con products
            $table->integer('quantity'); // cantidad del producto
            $table->decimal('price', 10, 2); // precio unitario
            $table->decimal('cost', 10, 2)->nullable(); // costo del producto (opcional)
            $table->timestamps(); // created_at y updated_at

            // Índices y claves foráneas
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('order_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
