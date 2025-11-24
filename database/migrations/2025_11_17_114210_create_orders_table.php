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
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id'); // id Primaria AUTO_INCREMENT
            $table->string('order_code'); // código del pedido
            $table->unsignedBigInteger('user_id'); // usuario que hizo el pedido
            $table->decimal('subtotal', 10, 2)->nullable(); // subtotal
            $table->decimal('total', 10, 2); // total
            $table->decimal('discount', 10, 2)->default(0.00); // descuento
            $table->decimal('tax', 10, 2)->default(0.00); // impuesto
            $table->decimal('tax_rate', 5, 2)->nullable(); // porcentaje de impuesto
            $table->string('shipping_address'); // dirección de envío
            $table->enum('payment_method', ['card', 'paypal', 'cash', 'bizum']); // método de pago
            $table->enum('status', ['pendiente', 'procesando', 'enviado', 'en_ruta', 'entregado', 'cancelado'])->default('pendiente'); // estado del pedido
            $table->string('tracking_number')->nullable(); // número de seguimiento
            $table->string('courier')->nullable(); // empresa de mensajería
            $table->dateTime('estimated_delivery_date')->nullable(); // fecha estimada de entrega
            $table->string('promo_code')->nullable(); // código promocional
            $table->enum('coupon_type', ['percent', 'fixed'])->nullable(); // tipo de cupón
            $table->string('mobile1')->nullable(); // teléfono principal
            $table->string('mobile2')->nullable(); // teléfono secundario
            $table->unsignedBigInteger('address_id')->nullable(); // id de dirección asociada
            $table->timestamps(); // created_at y updated_at

            // Índices y relaciones
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('address_id')->references('id')->on('addresses')->onDelete('set null');
            $table->index('user_id');
            $table->index('address_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
