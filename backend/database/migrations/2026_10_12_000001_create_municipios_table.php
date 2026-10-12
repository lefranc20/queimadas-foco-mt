<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            // Código IBGE de 7 dígitos (o mesmo da coluna "municipio_id" do INPE)
            $table->unsignedInteger('id')->primary();
            $table->string('nome');
            $table->string('nome_normalizado')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
