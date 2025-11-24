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
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id'); // id Primaria AUTO_INCREMENT
            $table->unsignedBigInteger('user_id'); // usuario que realizó el pago
            $table->unsignedBigInteger('order_id'); // pedido asociado
            $table->string('method'); // método de pago
            $table->string('status')->default('pending'); // estado del pago
            $table->timestamp('paid_at')->nullable(); // fecha de pago
            $table->string('provider')->nullable(); // proveedor del pago (ej: PayPal)
            $table->longText('meta')->nullable(); // datos adicionales en JSON u otro formato
            $table->decimal('amount', 10, 2); // monto pagado
            $table->string('transaction_id')->nullable(); // ID de transacción
            $table->timestamps(); // created_at y updated_at

            // Claves foráneas e índices
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index('user_id');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
