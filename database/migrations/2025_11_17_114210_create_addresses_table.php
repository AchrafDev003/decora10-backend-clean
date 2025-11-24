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
        Schema::create('addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 50)->default('shipping'); // shipping o billing
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100);
            $table->string('zipcode', 20);
            $table->string('country', 100)->default('España');
            $table->string('mobile1', 20);
            $table->string('mobile2', 20)->nullable();
            $table->text('additional_info')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Índices y claves foráneas
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
