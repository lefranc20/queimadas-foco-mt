<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servidor de dados do INPE
    |--------------------------------------------------------------------------
    |
    | Dados abertos do Programa Queimadas. Não exigem chave de API.
    | https://dataserver-coids.inpe.br/queimadas/queimadas/focos/csv/
    |
    */

    'url_base' => env('INPE_URL_BASE', 'https://dataserver-coids.inpe.br/queimadas/queimadas/focos/csv'),

    'timeout' => (int) env('INPE_TIMEOUT', 300),

    /*
    | Estado filtrado na importação, exatamente como aparece na coluna "estado".
    */

    'estado' => 'MATO GROSSO',

    'sigla_estado' => 'MT',

    /*
    | Satélite de referência usado pelo INPE nas séries históricas: o sensor
    | MODIS a bordo do AQUA, passagem da tarde. Os arquivos anuais "sat_ref"
    | não trazem a coluna "satelite", então ela é preenchida com este valor.
    */

    'satelite_referencia' => 'AQUA_M-T',

];
