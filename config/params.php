<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',

    'mistral' => [
        'api_key' => 'FLIcKhH06cAhsSX6he8YktkE8LoT3nwi',

        'base_url' => 'https://api.mistral.ai',
        'timeout' => 30,
        'embedding_model' => 'mistral-embed',
        'chat_model'      => 'mistral-small',
        'max_tokens'      => 500,
        'temperature'     => 0.2,
        'rate_limit_seconds' => 1.0,
    ],
];
