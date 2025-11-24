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
        Schema::create('coupons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->unique();
            $table->enum('type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('discount', 10, 2);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('used')->default(false);
            $table->integer('used_count')->default(0);
            $table->integer('max_uses')->nullable();
            $table->decimal('min_purchase', 10,2)->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('campaign', 100)->nullable();
            $table->enum('source', ['manual', 'newsletter', 'affiliate', 'system'])->default('manual');
            $table->enum('customer_type', ['all', 'cliente', 'cliente_fiel', 'admin', 'dueño'])->default('all');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();

            // Índices y claves foráneas
            $table->index('user_id');
            $table->index('product_id');
            $table->index('category_id');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
