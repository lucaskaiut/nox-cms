<?php

return [
    'default_status' => env('AI_PUBLISHER_DEFAULT_STATUS', 'published'),
    'min_content_length' => (int) env('AI_PUBLISHER_MIN_CONTENT_LENGTH', 200),
    'editorial' => [
        'company' => env('AI_EDITORIAL_COMPANY', 'Nox Soluções em Tecnologia'),
        'audience' => explode(',', env('AI_EDITORIAL_AUDIENCE', 'empresas,empreendedores,gestores')),
        'tone' => env('AI_EDITORIAL_TONE', 'profissional'),
        'content_rules' => [
            'Usar headings H2 e H3.', 'Meta description entre 120 e 320 caracteres.',
            'Resumo (excerpt) com até 400 caracteres.', 'Evitar conteúdo superficial.',
            'Incluir exemplos práticos.', 'Parágrafos com no máximo 4-5 linhas.',
            'Linguagem clara e objetiva.',
        ],
    ],
];
