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

    /*
    | Por quantas horas os focos dos arquivos de 10 minutos ficam guardados.
    */

    'horas_recentes' => 72,

    /*
    | Referência de FRP (MW) por satélite: mediana, 90% e 99% dos focos de MT em
    | 2026. Abaixo da mediana a intensidade é fraca; até 90%, moderada; até 99%,
    | forte; acima, muito forte. É uma escala deste projeto, não do INPE.
    */

    'frp_referencia' => [
        'GOES-19' => [93.7, 210.7, 599.5],
        'NOAA-20' => [6.4, 27, 121.1],
        'NOAA-21' => [7.5, 31.4, 149.5],
        'NPP-375' => [9.4, 36.5, 133.2],
        'NPP-375D' => [1.8, 6, 30.1],
        'AQUA_M-T' => [23.7, 102.4, 395.5],
        'AQUA_M-M' => [14.8, 61.9, 225.8],
        'TERRA_M-T' => [19.1, 84.4, 354.8],
        'TERRA_M-M' => [15.1, 67.7, 393.7],
    ],

    /*
    | Clima por município (Open-Meteo, CC BY 4.0, sem chave de API).
    */

    'url_clima' => env('CLIMA_URL', 'https://api.open-meteo.com/v1/forecast'),

];
