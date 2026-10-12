<?php

namespace Database\Seeders;

use App\Models\Municipio;
use Illuminate\Database\Seeder;

/**
 * Os 142 municípios de Mato Grosso com o código do IBGE
 * (fonte: servicodados.ibge.gov.br/api/v1/localidades/estados/51/municipios).
 */
class MunicipioSeeder extends Seeder
{
    public function run(): void
    {
        $municipios = json_decode(file_get_contents(database_path('data/municipios_mt.json')), true);

        $linhas = array_map(fn (array $m) => [
            'id' => $m['id'],
            'nome' => $m['nome'],
            'nome_normalizado' => Municipio::normalizarNome($m['nome']),
        ], $municipios);

        Municipio::upsert($linhas, ['id'], ['nome', 'nome_normalizado']);
    }
}
