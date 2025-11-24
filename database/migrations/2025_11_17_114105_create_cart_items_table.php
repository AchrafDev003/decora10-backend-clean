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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->bigIncrements('id'); // id Primaria AUTO_INCREMENT
            $table->unsignedBigInteger('cart_id'); // relación con carrito
            $table->unsignedBigInteger('product_id'); // relación con producto
            $table->integer('quantity')->default(1); // cantidad
            $table->decimal('total_price', 10, 2); // precio total del item
            $table->timestamp('reserved_until')->nullable(); // reservado hasta
            $table->boolean('notified_expiry')->default(false); // si se notificó expiración
            $table->timestamps(); // created_at y updated_at

            // Índices y claves foráneas
            $table->foreign('cart_id')->references('id')->on('carts')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('cart_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
