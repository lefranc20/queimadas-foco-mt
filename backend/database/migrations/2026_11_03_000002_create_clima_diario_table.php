<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clima_diario', function (Blueprint $table) {
            $table->unsignedInteger('municipio_id');
            $table->date('data');
            $table->float('temperatura_media')->nullable();
            $table->float('temperatura_min')->nullable();
            $table->float('temperatura_max')->nullable();
            $table->float('umidade_media')->nullable();
            $table->float('chuva_mm')->nullable();
            $table->timestamps();

            $table->primary(['municipio_id', 'data']);
            $table->foreign('municipio_id')->references('id')->on('municipios');
            $table->index('data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clima_diario');
    }
};
