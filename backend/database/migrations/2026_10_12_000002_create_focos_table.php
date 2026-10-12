<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focos', function (Blueprint $table) {
            // UUID gerado pelo INPE; é a chave do upsert
            $table->uuid('id')->primary();
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->dateTime('data_hora_gmt');
            // Data (GMT) separada para agrupar e filtrar sem funções específicas de cada banco
            $table->date('data');
            $table->string('satelite', 20);
            $table->unsignedInteger('municipio_id')->nullable();
            $table->string('bioma', 30)->nullable();
            $table->unsignedSmallInteger('numero_dias_sem_chuva')->nullable();
            $table->float('precipitacao')->nullable();
            $table->float('risco_fogo')->nullable();
            $table->float('frp')->nullable();
            $table->timestamps();

            $table->foreign('municipio_id')->references('id')->on('municipios');
            $table->index(['data', 'satelite']);
            $table->index(['municipio_id', 'data']);
            $table->index(['bioma', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focos');
    }
};
