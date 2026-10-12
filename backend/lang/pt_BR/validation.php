<?php

// Apenas as regras usadas pela API; as demais caem no idioma de fallback (inglês).
return [
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'date_format' => 'O campo :attribute deve estar no formato :format.',
    'in' => 'O valor informado em :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
    ],
    'string' => 'O campo :attribute deve ser um texto.',

    'attributes' => [
        'inicio' => 'início',
    ],
];
