<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            $table->string('arquivo');
            $table->string('status', 20);
            $table->unsignedInteger('registros')->default(0);
            $table->unsignedInteger('novos')->default(0);
            $table->unsignedInteger('atualizados')->default(0);
            $table->text('mensagem')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'concluida_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes');
    }
};
