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
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->bigIncrements('id'); // id Primaria AUTO_INCREMENT
            $table->unsignedBigInteger('order_id'); // relación con orders
            $table->enum('status', ['pendiente', 'procesando', 'enviado', 'en_ruta', 'entregado', 'cancelado']); // estado del pedido
            $table->text('nota')->nullable(); // nota opcional
            $table->timestamp('cambiado_en')->useCurrent(); // timestamp del cambio
            $table->timestamps(); // created_at y updated_at

            // Clave foránea e índice
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
