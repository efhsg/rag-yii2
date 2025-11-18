<?php

$local = [];
$localFile = __DIR__ . '/params-local.php';

if (is_file($localFile)) {
    $local = require $localFile;
}

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',

    'mistral' => [
        // volgorde:
        // 1) params-local.php
        // 2) environment variable
        // 3) leeg (foutmelding in UI/CLI)
        'api_key' => $local['mistral']['api_key']
            ?? getenv('MISTRAL_API_KEY')
            ?: '',

        'base_url' => 'https://api.mistral.ai',
        'timeout' => 30,
        'embedding_model' => 'mistral-embed',
        'chat_model' => 'mistral-small',
        'max_tokens' => 500,
        'temperature' => 0.2,
        'rate_limit_seconds' => 1.0,
    ],
];
