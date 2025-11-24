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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id'); // id Primaria AUTO_INCREMENT
            $table->string('name'); // name varchar(255)
            $table->string('email')->unique(); // email varchar(255) con índice único
            $table->string('provider')->nullable(); // provider varchar(255) nullable
            $table->string('provider_id')->nullable(); // provider_id varchar(255) nullable
            $table->timestamp('email_verified_at')->nullable(); // email_verified_at timestamp nullable
            $table->string('password'); // password varchar(255)
            $table->string('email_verification_token', 60)->nullable(); // token 60 caracteres nullable
            $table->string('photo')->nullable(); // photo varchar(255) nullable
            $table->enum('role', ['admin', 'dueno', 'cliente', 'cliente_fiel'])->default('cliente'); // role enum
            $table->timestamp('fecha_validacion')->nullable(); // fecha_validacion timestamp nullable
            $table->rememberToken(); // remember_token varchar(100) nullable
            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
