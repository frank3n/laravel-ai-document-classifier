<?php

return [

    'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    | Keys become the valid category values Claude must choose from.
    | Add/remove entries here — no code changes required.
    */
    'categories' => [
        'invoice' => [
            'label'       => 'Invoice',
            'description' => 'A bill or request for payment for goods or services.',
        ],
        'contract' => [
            'label'       => 'Contract',
            'description' => 'A legal agreement between two or more parties.',
        ],
        'support_request' => [
            'label'       => 'Support Request',
            'description' => 'A customer help request, complaint, or question.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra Instructions
    |--------------------------------------------------------------------------
    | Appended to the classification prompt. Override via .env to tune
    | behaviour without redeploying.
    */
    'extra_instructions' => env(
        'CLASSIFIER_EXTRA_INSTRUCTIONS',
        'If the document does not clearly fit any category, choose the closest match and set confidence to "low".'
    ),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */
    'routing' => [
        'webhook' => [
            'enabled' => (bool) env('WEBHOOK_ENABLED', false),
            'url'     => env('WEBHOOK_URL', ''),
            'secret'  => env('WEBHOOK_SECRET', ''),
        ],
        'slack' => [
            'enabled'     => (bool) env('SLACK_ENABLED', false),
            'webhook_url' => env('SLACK_WEBHOOK_URL', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Claude API
    |--------------------------------------------------------------------------
    */
    'api' => [
        'key'        => env('ANTHROPIC_API_KEY'),
        'base_url'   => 'https://api.anthropic.com/v1/messages',
        'version'    => '2023-06-01',
        'max_tokens' => 512,
        'timeout'    => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_size_kb'        => 5120,
        'allowed_extensions' => ['txt', 'pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG (Retrieval-Augmented Generation)
    |--------------------------------------------------------------------------
    | Drop .txt or .pdf files into storage/app/rag/ then run:
    |   php artisan rag:index
    |
    | Pass --rag to the Artisan command or use_rag=true in the POST body
    | to inject relevant context into the classification prompt.
    */
    'rag' => [
        'knowledge_base_path' => 'rag',   // relative to storage/app/
        'chunk_size'          => 300,     // words per chunk
        'top_n'               => 3,       // chunks to inject per request
    ],

];
