<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Detecções da pasta 10min/ do INPE. É uma tabela temporária: os arquivos não trazem
        // id, FRP nem município, e o dado definitivo chega depois pelo arquivo diário (focos).
        Schema::create('focos_recentes', function (Blueprint $table) {
            // md5 de lat, lon, data e satélite: o INPE não fornece id nesses arquivos
            $table->char('chave', 32)->primary();
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->dateTime('data_hora_gmt')->index();
            $table->string('satelite', 20);
            $table->unsignedInteger('municipio_id');
            $table->timestamps();

            $table->foreign('municipio_id')->references('id')->on('municipios');
            $table->index(['municipio_id', 'data_hora_gmt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focos_recentes');
    }
};
